@echo off
rem Starts everything the system needs, each in its own window:
rem   1. web server        (php artisan serve)
rem   2. queue worker      (push notifications, digitize hardcopy, notification bell)
rem   3. scheduler         (scheduled backups, trash purge)
rem Double-click this file. Close the windows to stop.

cd /d "%~dp0"

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

rem Child windows inherit OPENSSL_CONF from this one.
rem The queue worker exits if MySQL goes away (e.g. MySQL stopped or
rem restarted), so it restarts itself; otherwise notifications and other
rem queued jobs silently wait until someone notices.
start "Server" cmd /k php artisan serve
start "Queue worker" cmd /q /k "for /l %%n in (0,0,1) do (php artisan queue:work & echo. & echo Queue worker stopped - restarting in 10 seconds. Close this window to stop it. & timeout /t 10 /nobreak >nul)"
start "Scheduler" cmd /k php artisan schedule:work

echo.
echo Started server, queue worker and scheduler. You can close this window.
timeout /t 5 >nul
