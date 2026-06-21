[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

function Find-DatabaseClient {
    $commands = @('mysql.exe', 'mariadb.exe')
    $roots = @(
        'C:\Program Files',
        'C:\Program Files (x86)',
        'C:\xampp',
        'C:\OSPanel',
        'C:\OpenServer'
    )

    foreach ($command in @('mysql', 'mariadb')) {
        $resolved = Get-Command $command -ErrorAction SilentlyContinue
        if ($resolved -and $resolved.Source -and (Test-Path $resolved.Source)) {
            return $resolved.Source
        }
    }

    foreach ($root in $roots) {
        if (-not (Test-Path $root)) {
            continue
        }

        foreach ($command in $commands) {
            $found = Get-ChildItem -Path $root -Filter $command -Recurse -ErrorAction SilentlyContinue |
                Select-Object -First 1
            if ($found) {
                return $found.FullName
            }
        }
    }

    return $null
}

function Start-DatabaseService {
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

$existingClient = Find-DatabaseClient
if ($existingClient) {
    Start-DatabaseService
    Write-Host "Database client already available: $existingClient"
    exit 0
}

$winget = Get-Command winget -ErrorAction SilentlyContinue
if (-not $winget -or -not $winget.Source) {
    throw 'winget was not found. Install App Installer from Microsoft Store or install MariaDB manually.'
}

Write-Host 'Installing MariaDB Server via winget...'
& $winget.Source install --id MariaDB.Server --exact --silent --accept-package-agreements --accept-source-agreements
if ($LASTEXITCODE -ne 0) {
    throw "MariaDB installation failed with exit code $LASTEXITCODE. Try running this script as Administrator."
}

Start-Sleep -Seconds 5
Start-DatabaseService

$installedClient = Find-DatabaseClient
if (-not $installedClient) {
    throw 'MariaDB was installed, but mysql.exe/mariadb.exe was not found afterwards.'
}

Write-Host "Database client installed: $installedClient"
Write-Host 'Next step:'
Write-Host '.\first-run.cmd'
