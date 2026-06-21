[CmdletBinding()]
param(
    [string]$PhpPath = '',
    [string]$ListenHost = '127.0.0.1',
    [int]$Port = 8016
)

$ErrorActionPreference = 'Stop'

$ini = Join-Path $PSScriptRoot 'php.local.ini'

function Resolve-PhpExecutable {
    param([string]$RequestedPath)

    if ($RequestedPath) {
        if (Test-Path $RequestedPath) {
            return (Resolve-Path $RequestedPath).Path
        }

        throw "PHP not found: $RequestedPath"
    }

    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command -and $command.Source -and (Test-Path $command.Source)) {
        return $command.Source
    }

    $candidates = @(
        'C:\OSPanel\modules\php\PHP_8.1\php.exe',
        'C:\OSPanel\modules\php\PHP_8.2\php.exe',
        'C:\OSPanel\modules\php\PHP_8.3\php.exe',
        'C:\OpenServer\modules\php\PHP_8.1\php.exe',
        'C:\OpenServer\modules\php\PHP_8.2\php.exe',
        'C:\OpenServer\modules\php\PHP_8.3\php.exe',
        'C:\xampp\php\php.exe',
        'C:\Program Files\PHP\8.5.7\nts\x64\php.exe',
        'C:\Program Files\PHP\8.5\nts\x64\php.exe'
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return (Resolve-Path $candidate).Path
        }
    }

    throw 'PHP executable not found. Run .\first-run.ps1 first or pass -PhpPath.'
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

$php = Resolve-PhpExecutable -RequestedPath $PhpPath

Update-PhpIni -IniPath $ini -PhpExePath $php

Write-Host "Starting local server with PHP: $php"
Write-Host "Using config: $ini"
& $php -c $ini -S "$ListenHost`:$Port"
