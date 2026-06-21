@echo off
setlocal
set "PS1_FILE=%~dp0first-run.ps1"

if not exist "%PS1_FILE%" (
    echo first-run.ps1 not found: "%PS1_FILE%"
    echo Copy the full project, including *.ps1, config and database folders.
    endlocal ^& exit /b 1
)
powershell -NoProfile -ExecutionPolicy Bypass -File "%PS1_FILE%" %*
set EXIT_CODE=%ERRORLEVEL%
endlocal & exit /b %EXIT_CODE%
