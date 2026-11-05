@echo off
rem Starts everything the system needs, each in its own window:
rem   1. web server        (php artisan serve)
rem   2. queue worker      (push notifications, notification bell, backups)
rem   3. OCR worker        (reads uploaded files, digitize hardcopy)
rem   4. scheduler         (scheduled backups, trash purge)
rem Once install-services.bat has been run, 2-4 are Windows services that
rem start with Windows, and this only opens the server window.
rem Double-click this file. Close the windows to stop.

cd /d "%~dp0"

start "Server" cmd /k php artisan serve

rem Find openssl.cnf next to the php.exe that is on PATH (XAMPP: php\extras\ssl).
for /f "delims=" %%i in ('where php') do (
    if not defined PHPDIR set "PHPDIR=%%~dpi"
)

if not defined OPENSSL_CONF (
    if exist "%PHPDIR%extras\ssl\openssl.cnf" set "OPENSSL_CONF=%PHPDIR%extras\ssl\openssl.cnf"
)

sc query qsu-fmas-queue >nul 2>&1 && goto services

if not defined OPENSSL_CONF (
    echo WARNING: openssl.cnf not found. Push notifications will fail.
    echo Set OPENSSL_CONF to its full path and run this again.
) else (
    echo Using OPENSSL_CONF=%OPENSSL_CONF%
)

rem Child windows inherit OPENSSL_CONF from this one.
rem The workers exit if MySQL goes away (e.g. MySQL stopped or restarted),
rem so they restart themselves; otherwise notifications and other queued
rem jobs silently wait until someone notices.
start "Queue worker" cmd /q /k "for /l %%n in (0,0,1) do (php artisan queue:work & echo. & echo Queue worker stopped - restarting in 10 seconds. Close this window to stop it. & timeout /t 10 /nobreak >nul)"
start "OCR worker" cmd /q /k "for /l %%n in (0,0,1) do (php artisan queue:work --queue=ocr & echo. & echo OCR worker stopped - restarting in 10 seconds. Close this window to stop it. & timeout /t 10 /nobreak >nul)"
start "Scheduler" cmd /k php artisan schedule:work

echo.
echo Started server, queue worker, OCR worker and scheduler. You can close this window.
timeout /t 5 >nul
exit /b

:services
rem Services installed before the OCR worker existed (2026-11-03) have no OCR
rem service: it runs in a window instead until install-services.bat is run again.
set "OCR_SERVICE=qsu-fmas-queue-ocr"
sc query qsu-fmas-queue-ocr >nul 2>&1 || set "OCR_SERVICE="

echo.
echo Started the server. These run as Windows services:
for %%s in (qsu-fmas-queue %OCR_SERVICE% qsu-fmas-scheduler) do (
    sc query %%s | find "RUNNING" >nul && echo   %%s - running|| echo   %%s - NOT RUNNING - start it in Services ^(services.msc^)
)

if not defined OCR_SERVICE (
    start "OCR worker" cmd /q /k "for /l %%n in (0,0,1) do (php artisan queue:work --queue=ocr & echo. & echo OCR worker stopped - restarting in 10 seconds. Close this window to stop it. & timeout /t 10 /nobreak >nul)"
    echo.
    echo The OCR worker is not a service yet, so it runs in the "OCR worker" window.
    echo Run install-services.bat again to make it a service.
)
timeout /t 10 >nul
