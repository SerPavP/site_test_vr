[CmdletBinding()]
param(
    [string]$PhpPath = '',
    [string]$MysqlPath = '',
    [string]$DbHost = '127.0.0.1',
    [int]$DbPort = 3306,
    [string]$DbName = 'pdd_test',
    [string]$DbUser = 'root',
    [string]$DbPassword = '',
    [string]$ListenHost = '127.0.0.1',
    [int]$SitePort = 8016,
    [switch]$SkipDbInstall
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSCommandPath
$configSamplePath = Join-Path $projectRoot 'config\config.sample.php'
$configPath = Join-Path $projectRoot 'config\config.php'
$schemaPath = Join-Path $projectRoot 'database\schema.sql'
$phpLocalIniPath = Join-Path $projectRoot 'php.local.ini'

function Find-Executable {
    param(
        [string]$RequestedPath,
        [string[]]$CommandNames,
        [string[]]$CandidatePaths,
        [string]$DisplayName,
        [string[]]$SearchRoots = @(),
        [switch]$AllowMissing
    )

    if ($RequestedPath) {
        if (Test-Path $RequestedPath) {
            return (Resolve-Path $RequestedPath).Path
        }

        throw "$DisplayName not found: $RequestedPath"
    }

    foreach ($commandName in $CommandNames) {
        $command = Get-Command $commandName -ErrorAction SilentlyContinue
        if ($command -and $command.Source -and (Test-Path $command.Source)) {
            return $command.Source
        }
    }

    foreach ($candidate in $CandidatePaths) {
        if (Test-Path $candidate) {
            return (Resolve-Path $candidate).Path
        }
    }

    foreach ($root in $SearchRoots) {
        if (-not (Test-Path $root)) {
            continue
        }

        foreach ($commandName in $CommandNames) {
            $found = Get-ChildItem -Path $root -Filter "$commandName.exe" -Recurse -ErrorAction SilentlyContinue |
                Select-Object -First 1
            if ($found) {
                return $found.FullName
            }
        }
    }

    $manualHint = switch ($DisplayName) {
        'PHP executable' { '.\first-run.cmd -PhpPath "C:\path\to\php.exe"' }
        'MySQL executable' { '.\first-run.cmd -MysqlPath "C:\path\to\mysql.exe"' }
        default { '.\first-run.cmd' }
    }

    if ($AllowMissing) {
        return $null
    }

    throw @"
$DisplayName was not found automatically.

Pass the path manually:
  $manualHint
"@
}

function Install-DatabaseServer {
    param([int]$Port)

    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if (-not $winget -or -not $winget.Source) {
        throw @"
winget was not found on this PC.

Install MariaDB manually or install App Installer from Microsoft Store,
then run:
  .\install-db.cmd
"@
    }

    Write-Host 'Installing MariaDB Server via winget...'
    & $winget.Source install --id MariaDB.Server --exact --silent --accept-package-agreements --accept-source-agreements
    if ($LASTEXITCODE -ne 0) {
        throw @"
MariaDB installation failed with exit code $LASTEXITCODE.

Try running PowerShell as Administrator and then:
  .\install-db.cmd
"@
    }

    Start-Sleep -Seconds 5

    $service = Get-Service |
        Where-Object {
            $_.Name -like 'MariaDB*' -or
            $_.DisplayName -like '*MariaDB*' -or
            $_.Name -like 'MySQL*' -or
            $_.DisplayName -like '*MySQL*'
        } |
        Select-Object -First 1

    if ($service -and $service.Status -ne 'Running') {
        Write-Host "Starting database service: $($service.Name)"
        Start-Service -Name $service.Name
    }
}

function Update-PhpIni {
    param(
        [string]$IniPath,
        [string]$PhpExePath
    )

    $extensionDir = Join-Path (Split-Path -Parent $PhpExePath) 'ext'
    $content = @(
        'extension_dir="' + ($extensionDir -replace '\\', '/') + '"'
        ''
        'extension=mysqli'
        'extension=pdo_mysql'
        ''
        'date.timezone=Asia/Yekaterinburg'
        'default_charset="UTF-8"'
        'display_errors=On'
        'display_startup_errors=On'
        'log_errors=On'
        'error_reporting=E_ALL'
    )

    Set-Content -Path $IniPath -Value $content -Encoding ASCII
}

function Update-ConfigFile {
    param(
        [string]$SourcePath,
        [string]$DestinationPath,
        [hashtable]$Values
    )

    $content = Get-Content $SourcePath -Raw
    $content = [regex]::Replace($content, "'base_url'\s*=>\s*'[^']*'", "'base_url' => '$($Values.BaseUrl)'")
    $content = [regex]::Replace($content, "'host'\s*=>\s*'[^']*'", "'host' => '$($Values.DbHost)'")
    $content = [regex]::Replace($content, "'port'\s*=>\s*'[^']*'", "'port' => '$($Values.DbPort)'")
    $content = [regex]::Replace($content, "'database'\s*=>\s*'[^']*'", "'database' => '$($Values.DbName)'")
    $content = [regex]::Replace($content, "'username'\s*=>\s*'[^']*'", "'username' => '$($Values.DbUser)'")
    $content = [regex]::Replace($content, "'password'\s*=>\s*'[^']*'", "'password' => '$($Values.DbPasswordEscaped)'")
    $content = [regex]::Replace($content, "'api_key'\s*=>\s*'[^']*'", "'api_key' => '$($Values.ApiKey)'")
    Set-Content -Path $DestinationPath -Value $content -Encoding UTF8
}

function Initialize-Database {
    param(
        [string]$MysqlExe,
        [string]$Host,
        [int]$Port,
        [string]$User,
        [string]$Password,
        [string]$SqlFile
    )

    $sql = Get-Content $SqlFile -Raw
    $oldPassword = $env:MYSQL_PWD

    try {
        if ($Password) {
            $env:MYSQL_PWD = $Password
        } else {
            Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
        }

        $sql | & $MysqlExe --protocol=TCP -h $Host -P $Port -u $User
        if ($LASTEXITCODE -ne 0) {
            throw "mysql exited with code $LASTEXITCODE"
        }
    } finally {
        if ($null -ne $oldPassword) {
            $env:MYSQL_PWD = $oldPassword
        } else {
            Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
        }
    }
}

$phpExe = Find-Executable `
    -RequestedPath $PhpPath `
    -CommandNames @('php') `
    -CandidatePaths @(
        'C:\OSPanel\modules\php\PHP_8.1\php.exe',
        'C:\OSPanel\modules\php\PHP_8.2\php.exe',
        'C:\OSPanel\modules\php\PHP_8.3\php.exe',
        'C:\OpenServer\modules\php\PHP_8.1\php.exe',
        'C:\OpenServer\modules\php\PHP_8.2\php.exe',
        'C:\OpenServer\modules\php\PHP_8.3\php.exe',
        'C:\xampp\php\php.exe',
        'C:\Program Files\PHP\8.5.7\nts\x64\php.exe',
        'C:\Program Files\PHP\8.5\nts\x64\php.exe'
    ) `
    -DisplayName 'PHP executable' `
    -SearchRoots @(
        'C:\Program Files',
        'C:\Program Files (x86)'
    )

$mysqlExe = Find-Executable `
    -RequestedPath $MysqlPath `
    -CommandNames @('mysql', 'mariadb') `
    -CandidatePaths @(
        'C:\OSPanel\modules\database\MySQL-8.0\bin\mysql.exe',
        'C:\OSPanel\modules\database\MySQL-8.4\bin\mysql.exe',
        'C:\OpenServer\modules\database\MySQL-8.0\bin\mysql.exe',
        'C:\OpenServer\modules\database\MariaDB-10.6\bin\mysql.exe',
        'C:\xampp\mysql\bin\mysql.exe',
        'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe',
        'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe'
    ) `
    -DisplayName 'MySQL executable' `
    -SearchRoots @(
        'C:\Program Files',
        'C:\Program Files (x86)'
    ) `
    -AllowMissing

if (-not $mysqlExe -and -not $SkipDbInstall) {
    Install-DatabaseServer -Port $DbPort
    $mysqlExe = Find-Executable `
        -RequestedPath $MysqlPath `
        -CommandNames @('mysql', 'mariadb') `
        -CandidatePaths @(
            'C:\OSPanel\modules\database\MySQL-8.0\bin\mysql.exe',
            'C:\OSPanel\modules\database\MySQL-8.4\bin\mysql.exe',
            'C:\OpenServer\modules\database\MySQL-8.0\bin\mysql.exe',
            'C:\OpenServer\modules\database\MariaDB-10.6\bin\mysql.exe',
            'C:\xampp\mysql\bin\mysql.exe',
            'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe',
            'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe'
        ) `
        -DisplayName 'MySQL executable' `
        -SearchRoots @(
            'C:\Program Files',
            'C:\Program Files (x86)'
        ) `
        -AllowMissing
}

if (-not $mysqlExe) {
    throw @"
MySQL executable was not found automatically.

Install database server:
  .\install-db.cmd

Or pass the path manually:
  .\first-run.cmd -MysqlPath "C:\path\to\mysql.exe"
"@
}

if (-not (Test-Path $configSamplePath)) {
    throw "Config sample not found: $configSamplePath"
}

if (-not (Test-Path $schemaPath)) {
    throw "Schema file not found: $schemaPath"
}

Update-PhpIni -IniPath $phpLocalIniPath -PhpExePath $phpExe

$apiKey = 'pdd_' + [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
$dbPasswordEscaped = $DbPassword.Replace('\', '\\').Replace("'", "\'")
Update-ConfigFile -SourcePath $configSamplePath -DestinationPath $configPath -Values @{
    BaseUrl = "http://$ListenHost`:$SitePort/public"
    DbHost = $DbHost
    DbPort = $DbPort
    DbName = $DbName
    DbUser = $DbUser
    DbPasswordEscaped = $dbPasswordEscaped
    ApiKey = $apiKey
}

Initialize-Database `
    -MysqlExe $mysqlExe `
    -Host $DbHost `
    -Port $DbPort `
    -User $DbUser `
    -Password $DbPassword `
    -SqlFile $schemaPath

Write-Host ''
Write-Host 'First-run setup completed.'
Write-Host "PHP: $phpExe"
Write-Host "MySQL: $mysqlExe"
Write-Host "Config: $configPath"
Write-Host "Database: $DbName on $DbHost`:$DbPort"
Write-Host "Site URL: http://$ListenHost`:$SitePort/public"
Write-Host "API URL: http://$ListenHost`:$SitePort/api/submit_result.php"
Write-Host "API key: $apiKey"
Write-Host ''
Write-Host 'Next step:'
Write-Host '.\run-local.cmd'
