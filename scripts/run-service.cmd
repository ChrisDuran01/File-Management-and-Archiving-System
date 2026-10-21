@echo off
rem Run by the "QSU-FMAS Queue Worker" and "QSU-FMAS Scheduler" Windows
rem services (see install-services.bat) - not meant to be opened by hand.
rem   %1            the artisan command: queue:work or schedule:work
rem   PHP_EXE       full path to php.exe       } set on the service by
rem   OPENSSL_CONF  openssl.cnf for web push   } scripts\install-services.ps1

cd /d "%~dp0.."

rem The service starts a new output log each time it starts; delete old ones after 14 days.
forfiles /p storage\logs /m service-*-20*.log /d -14 /c "cmd /c del @path" >nul 2>&1

rem When the command exits (MySQL stopped, php artisan queue:restart, a crash)
rem run it again 10 seconds later. This loop does it, not NSSM: on this PC
rem NSSM hung instead of restarting php artisan queue:work after it exited.
:loop
rem XAMPP's MySQL is started by hand after a reboot. Wait for it instead of
rem failing and writing the same error to laravel.log every few seconds.
"%PHP_EXE%" scripts\wait-for-mysql.php
echo [%date% %time%] Starting php artisan %1
"%PHP_EXE%" artisan %1
echo [%date% %time%] php artisan %1 stopped - starting it again in 10 seconds
"%PHP_EXE%" -r "sleep(10);"
goto loop
