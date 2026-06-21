@echo off
setlocal
set "SCRIPT_DIR=%~dp0"
set "PS1_FILE=%SCRIPT_DIR%run-local.ps1"
set "TMP_DIR=%TEMP%\pdd-test-site"
set "INI_FILE=%TMP_DIR%\php.local.ini"

if exist "%PS1_FILE%" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%PS1_FILE%" %*
    set EXIT_CODE=%ERRORLEVEL%
    endlocal & exit /b %EXIT_CODE%
)

set "PHP_EXE="

for %%I in (
    "C:\Program Files\PHP\8.5.7\nts\x64\php.exe"
    "C:\Program Files\PHP\8.5\nts\x64\php.exe"
    "C:\xampp\php\php.exe"
    "C:\OSPanel\modules\php\PHP_8.1\php.exe"
    "C:\OSPanel\modules\php\PHP_8.2\php.exe"
    "C:\OSPanel\modules\php\PHP_8.3\php.exe"
    "C:\OpenServer\modules\php\PHP_8.1\php.exe"
    "C:\OpenServer\modules\php\PHP_8.2\php.exe"
    "C:\OpenServer\modules\php\PHP_8.3\php.exe"
) do (
    if exist %%~I (
        set "PHP_EXE=%%~I"
        goto :php_found
    )
)

for /f "delims=" %%I in ('where php 2^>nul') do (
    set "PHP_EXE=%%I"
    goto :php_found
)

echo PHP executable not found.
echo Install PHP or restore run-local.ps1.
endlocal & exit /b 1

:php_found
if not exist "%TMP_DIR%" mkdir "%TMP_DIR%" >nul 2>nul
if not exist "%TMP_DIR%" (
    echo Failed to create temp directory: "%TMP_DIR%"
    endlocal & exit /b 1
)

if not exist "%INI_FILE%" (
    > "%INI_FILE%" echo extension_dir="%PHP_EXE:\php.exe=\ext%"
    >> "%INI_FILE%" echo.
    >> "%INI_FILE%" echo extension=mysqli
    >> "%INI_FILE%" echo extension=pdo_mysql
    >> "%INI_FILE%" echo.
    >> "%INI_FILE%" echo date.timezone=Asia/Yekaterinburg
    >> "%INI_FILE%" echo default_charset="UTF-8"
    >> "%INI_FILE%" echo display_errors=On
    >> "%INI_FILE%" echo display_startup_errors=On
    >> "%INI_FILE%" echo log_errors=On
    >> "%INI_FILE%" echo error_reporting=E_ALL
)

echo Starting local server with PHP: %PHP_EXE%
echo Using config: %INI_FILE%
"%PHP_EXE%" -c "%INI_FILE%" -S 127.0.0.1:8016
set EXIT_CODE=%ERRORLEVEL%
endlocal & exit /b %EXIT_CODE%
