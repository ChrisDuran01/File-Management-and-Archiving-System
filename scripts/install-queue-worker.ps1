# Installs `php artisan queue:work` as a persistent, auto-restarting Windows
# Service. Required for: backups (BackupController -> RunBackupJob), the
# digitize-hardcopy scan pipeline, and any queued notification (BackupFailed,
# FileUploadedToYourFolder, etc.) - none of these run at all without a queue
# worker actually running continuously.
#
# Run this in an Administrator PowerShell window
# (right-click PowerShell -> "Run as administrator"), from anywhere:
#   powershell -ExecutionPolicy Bypass -File scripts\install-queue-worker.ps1

$ErrorActionPreference = 'Stop'

$phpPath     = "C:\xampp\php\php.exe"
$appPath     = Split-Path -Parent $PSScriptRoot
$serviceName = "LaravelQueueWorker"

if (-not (Get-Command nssm -ErrorAction SilentlyContinue)) {
    Write-Host "Installing NSSM via Chocolatey..."
    choco install nssm -y
}

if (Get-Service -Name $serviceName -ErrorAction SilentlyContinue) {
    Write-Host "Service already exists - stopping and removing it first so this script can be re-run safely."
    nssm stop $serviceName
    nssm remove $serviceName confirm
}

Write-Host "Registering $serviceName against $appPath ..."
nssm install $serviceName $phpPath "artisan queue:work --sleep=3 --tries=3"
nssm set $serviceName AppDirectory $appPath
nssm set $serviceName AppStdout "$appPath\storage\logs\queue-worker.log"
nssm set $serviceName AppStderr "$appPath\storage\logs\queue-worker-error.log"
nssm set $serviceName Start SERVICE_AUTO_START
# If the worker process ever dies (crash, `php.exe` killed, a Windows
# update reboot, etc.), restart it after 5s rather than leaving jobs -
# backups included - stuck unprocessed until someone notices.
nssm set $serviceName AppExit Default Restart
nssm set $serviceName AppRestartDelay 5000
nssm set $serviceName DisplayName "Laravel Queue Worker (chrisDuran)"
nssm set $serviceName Description "Runs php artisan queue:work continuously - required for backups, digitize-hardcopy processing, and queued notifications."

Write-Host "Starting $serviceName..."
nssm start $serviceName

Start-Sleep -Seconds 2
sc.exe query $serviceName
