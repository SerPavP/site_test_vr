[CmdletBinding()]
param(
    [string]$ListenHost = '127.0.0.1',
    [int]$Port = 8081,
    [string]$PhpVersion = '8.3.30'
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSCommandPath
$toolsDir = Join-Path $projectRoot '.tools'
$phpDir = Join-Path $toolsDir 'php'
$phpZip = Join-Path $toolsDir 'php.zip'
$runtimePhpIni = Join-Path $toolsDir 'php-runtime.ini'
$configPath = Join-Path $projectRoot 'config\config.php'
$databasePath = Join-Path $projectRoot 'database\pdd.sqlite'
$logsDir = Join-Path $projectRoot 'logs'
$sessionsDir = Join-Path $logsDir 'sessions'
$phpUrls = @(
    "https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip",
    "https://windows.php.net/downloads/releases/php-$PhpVersion-nts-Win32-vs16-x64.zip"
)

New-Item -ItemType Directory -Force -Path $toolsDir, $phpDir, $logsDir, $sessionsDir, (Split-Path $databasePath -Parent) | Out-Null

function Resolve-PhpExecutable
{
    $portablePhp = Join-Path $projectRoot '.tools\php\php.exe'
    if (Test-Path $portablePhp) {
        return $portablePhp
    }

    throw 'Portable PHP executable not found in .tools\php\php.exe.'
}

function Download-PhpArchive
{
    param(
        [string[]]$Urls,
        [string]$Destination
    )

    $errors = New-Object System.Collections.Generic.List[string]

    foreach ($url in $Urls) {
        Write-Host "Trying PHP download: $url"

        try {
            if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
                & curl.exe -L --fail --output $Destination $url
                if ($LASTEXITCODE -eq 0 -and (Test-Path $Destination) -and (Get-Item $Destination).Length -gt 0) {
                    return
                }
                $errors.Add("curl failed for ${url} with exit code $LASTEXITCODE")
            }
        } catch {
            $errors.Add("curl exception for ${url}: $($_.Exception.Message)")
        }

        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            Invoke-WebRequest -Uri $url -OutFile $Destination -UseBasicParsing
            if ((Test-Path $Destination) -and (Get-Item $Destination).Length -gt 0) {
                return
            }
            $errors.Add("Invoke-WebRequest downloaded an empty file for ${url}")
        } catch {
            $errors.Add("Invoke-WebRequest failed for ${url}: $($_.Exception.Message)")
        }
    }

    $details = $errors -join ' | '
    throw @"
Failed to download PHP archive from windows.php.net.

What to do next:
1. Download PHP manually:
   https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip
2. Save the archive as:
   $Destination
   or extract it to:
   $phpDir
3. Re-run first-run.ps1

Original errors:
$details
"@
}

$portablePhpExe = Join-Path $phpDir 'php.exe'
if (-not (Test-Path $portablePhpExe)) {
    if (Test-Path $phpZip) {
        Write-Host "Using local PHP archive: $phpZip"
    } else {
        Write-Host "Downloading PHP $PhpVersion..."
        Download-PhpArchive -Urls $phpUrls -Destination $phpZip
    }

    if (Test-Path $phpDir) {
        Get-ChildItem -Path $phpDir -Force -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
    }

    Expand-Archive -Path $phpZip -DestinationPath $phpDir -Force
}

$phpExe = Resolve-PhpExecutable
$sourcePhpDir = Split-Path $phpExe -Parent
$sourcePhpIni = Join-Path $sourcePhpDir 'php.ini'

if (-not (Test-Path $sourcePhpIni)) {
    $iniProduction = Join-Path $sourcePhpDir 'php.ini-production'
    if (Test-Path $iniProduction) {
        $sourcePhpIni = $iniProduction
    } else {
        throw "php.ini not found рядом с $phpExe"
    }
}

$phpIniContent = Get-Content $sourcePhpIni -Raw
$phpIniContent = $phpIniContent -replace '(?m)^;?\s*extension_dir\s*=.*$', 'extension_dir = "ext"'
$phpIniContent = $phpIniContent -replace '(?m)^;?\s*extension=pdo_sqlite\s*$', 'extension=pdo_sqlite'
$phpIniContent = $phpIniContent -replace '(?m)^;?\s*extension=sqlite3\s*$', 'extension=sqlite3'
$phpIniContent = $phpIniContent -replace '(?m)^;?\s*date\.timezone\s*=.*$', 'date.timezone = Asia/Yekaterinburg'
Set-Content -Path $runtimePhpIni -Value $phpIniContent -Encoding ASCII

$apiKey = 'pdd_' + [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
$configContent = @"
<?php
return [
    'app_name' => 'PDD Test',
    'base_url' => 'http://$ListenHost`:$Port/public',
    'db' => [
        'driver' => 'sqlite',
        'sqlite_path' => __DIR__ . '/../database/pdd.sqlite',
        'mysql' => [
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'pdd_test',
            'username' => 'root',
            'password' => '1212',
            'charset' => 'utf8mb4',
        ],
    ],
    'admin' => [
        'username' => 'admin',
        'password' => 'admin123',
    ],
    'security' => [
        'api_key' => '$apiKey',
        'rate_limit_per_5_min' => 30,
    ],
];
"@
Set-Content -Path $configPath -Value $configContent -Encoding UTF8

$tempInitScript = Join-Path $toolsDir 'init_sqlite.php'
$initScript = @'
<?php
$databasePath = __DIR__ . '/../database/pdd.sqlite';
$schemaPath = __DIR__ . '/../database/schema_sqlite.sql';
$pdo = new PDO('sqlite:' . $databasePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$sql = file_get_contents($schemaPath);
if ($sql === false) {
    fwrite(STDERR, "Failed to read schema file.\n");
    exit(1);
}
$pdo->exec($sql);
echo "SQLite database initialized: $databasePath\n";
'@
Set-Content -Path $tempInitScript -Value $initScript -Encoding ASCII

try {
    & $phpExe -c $runtimePhpIni $tempInitScript
    if ($LASTEXITCODE -ne 0) {
        throw "SQLite initialization failed with exit code $LASTEXITCODE."
    }
} finally {
    if (Test-Path $tempInitScript) {
        Remove-Item $tempInitScript -Force
    }
}

Write-Host ''
Write-Host 'First-run setup completed.'
Write-Host "PHP: $phpExe"
Write-Host "PHP ini: $runtimePhpIni"
Write-Host "Config: $configPath"
Write-Host "SQLite: $databasePath"
Write-Host "URL: http://$ListenHost`:$Port/public"
Write-Host "API URL: http://$ListenHost`:$Port/api/submit_result.php"
Write-Host ''
Write-Host 'Next step:'
Write-Host "powershell -ExecutionPolicy Bypass -File `"$projectRoot\start-app.ps1`""
