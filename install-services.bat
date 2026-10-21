@echo off
rem Runs the queue worker and the scheduler as Windows services: hidden,
rem started with Windows, restarted when they stop. See scripts\install-services.ps1.
rem Double-click this file; Windows asks for administrator rights.
rem To go back to the windows that start-all.bat opens: uninstall-services.bat

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\install-services.ps1"
pause
