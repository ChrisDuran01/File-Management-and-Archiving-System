@echo off
rem Starts the queue worker with OPENSSL_CONF set, so browser push notifications
rem (and everything else on the queue) work from the Windows/XAMPP command line.
rem Double-click this file, or run it from the project folder. Keep the window open.

cd /d "%~dp0"

sc query qsu-fmas-queue >nul 2>&1
if not errorlevel 1 (
    echo The queue worker already runs as a Windows service ^(install-services.bat^).
    echo To make it pick up code or .env changes: php artisan queue:restart
    pause
    exit /b
)

rem Find openssl.cnf next to the php.exe that is on PATH (XAMPP: php\extras\ssl).
for /f "delims=" %%i in ('where php') do (
    if not defined PHPDIR set "PHPDIR=%%~dpi"
)

if not defined OPENSSL_CONF (
    if exist "%PHPDIR%extras\ssl\openssl.cnf" set "OPENSSL_CONF=%PHPDIR%extras\ssl\openssl.cnf"
)

if not defined OPENSSL_CONF (
    echo WARNING: openssl.cnf not found. Push notifications will fail.
    echo Set OPENSSL_CONF to its full path and run this again.
) else (
    echo Using OPENSSL_CONF=%OPENSSL_CONF%
)

php artisan queue:work
pause
