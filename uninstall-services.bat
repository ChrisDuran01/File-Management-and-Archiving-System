@echo off
rem Removes the queue worker and scheduler Windows services that
rem install-services.bat made. start-all.bat then opens them in windows again.
rem Double-click this file; Windows asks for administrator rights.

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\install-services.ps1" -Uninstall
pause
