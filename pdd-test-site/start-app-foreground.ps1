[CmdletBinding()]
param(
    [string]$ListenHost = '127.0.0.1',
    [int]$Port = 8081
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSCommandPath
$runtimePhpIni = Join-Path $projectRoot '.tools\php-runtime.ini'

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

Write-Host "PHP server is starting in foreground mode."
Write-Host "URL: http://$ListenHost`:$Port/public"
Write-Host "API URL: http://$ListenHost`:$Port/api/submit_result.php"
Write-Host 'To stop the server, press Ctrl+C in this window.'
Write-Host ''

Set-Location $projectRoot
& $phpExe -c $phpIni -S "$ListenHost`:$Port"
