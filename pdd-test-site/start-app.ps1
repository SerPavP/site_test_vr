[CmdletBinding()]
param(
    [string]$ListenHost = '127.0.0.1',
    [int]$Port = 8081
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSCommandPath
$runtimePhpIni = Join-Path $projectRoot '.tools\php-runtime.ini'
$logsDir = Join-Path $projectRoot 'logs'
$pidFile = Join-Path $logsDir 'php-server.pid'
$stdoutLog = Join-Path $logsDir 'php-server.stdout.log'
$stderrLog = Join-Path $logsDir 'php-server.stderr.log'

function Resolve-PhpExecutable
{
    $candidates = @(
        (Join-Path $projectRoot '.tools\php\php.exe'),
        'C:\OSPanel\modules\php\PHP_8.0\php.exe'
    )

    foreach ($candidate in $candidates) {
        if ($candidate -and (Test-Path $candidate)) {
            return $candidate
        }
    }

    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    throw "PHP not found. Run first-run.ps1 first."
}

$phpExe = Resolve-PhpExecutable
$phpIni = if (Test-Path $runtimePhpIni) { $runtimePhpIni } else { Join-Path (Split-Path $phpExe -Parent) 'php.ini' }

New-Item -ItemType Directory -Force -Path $logsDir | Out-Null

if (Test-Path $pidFile) {
    $existingPid = (Get-Content $pidFile -Raw).Trim()
    if ($existingPid) {
        $runningProcess = Get-Process -Id $existingPid -ErrorAction SilentlyContinue
        if ($runningProcess) {
            Write-Host "PHP server is already running. PID: $existingPid"
            Write-Host "URL: http://$ListenHost`:$Port/public"
            exit 0
        }
    }

    Remove-Item $pidFile -Force
}

$arguments = @('-c', $phpIni, '-S', "$ListenHost`:$Port")
$process = Start-Process -FilePath $phpExe -ArgumentList $arguments -WorkingDirectory $projectRoot -RedirectStandardOutput $stdoutLog -RedirectStandardError $stderrLog -WindowStyle Hidden -PassThru

Start-Sleep -Seconds 2

if ($process.HasExited) {
    $stderr = if (Test-Path $stderrLog) { Get-Content $stderrLog -Raw } else { '' }
    throw "PHP server failed to start. $stderr"
}

Set-Content -Path $pidFile -Value $process.Id -Encoding ASCII

Write-Host "PHP server started. PID: $($process.Id)"
Write-Host "URL: http://$ListenHost`:$Port/public"
Write-Host "API URL: http://$ListenHost`:$Port/api/submit_result.php"
Write-Host "Stop command: powershell -ExecutionPolicy Bypass -File `"$projectRoot\stop-app.ps1`""
