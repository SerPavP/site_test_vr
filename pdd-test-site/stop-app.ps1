[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSCommandPath
$pidFile = Join-Path $projectRoot 'logs\php-server.pid'

if (-not (Test-Path $pidFile)) {
    Write-Host 'PHP server is not running or PID file is missing.'
    exit 0
}

$serverPid = (Get-Content $pidFile -Raw).Trim()
if (-not $serverPid) {
    Remove-Item $pidFile -Force
    Write-Host 'PID file was empty and has been removed.'
    exit 0
}

$process = Get-Process -Id $serverPid -ErrorAction SilentlyContinue
if (-not $process) {
    Remove-Item $pidFile -Force
    Write-Host "Process $serverPid is already stopped. Stale PID file removed."
    exit 0
}

Stop-Process -Id $serverPid
Start-Sleep -Milliseconds 500

if (Test-Path $pidFile) {
    Remove-Item $pidFile -Force
}

Write-Host "PHP server stopped. PID: $serverPid"
