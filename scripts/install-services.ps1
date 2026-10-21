# Runs the queue worker and the scheduler as Windows services instead of the
# "Queue worker" / "Scheduler" windows that start-all.bat opens. They start
# with Windows (nobody has to log in), run hidden, and come back on their own
# when they stop. The web server (php artisan serve) is not included.
#
# Don't run this directly - double-click install-services.bat or
# uninstall-services.bat, which ask for administrator rights first.
# Safe to run again: existing services are removed and created fresh.
#
# The services run as LocalSystem, so everything they use must be given as a
# full path (.env already does this for the OCR tools and mysqldump).

param([switch] $Uninstall)

$ErrorActionPreference = 'Stop'
$ProgressPreference    = 'SilentlyContinue' # Invoke-WebRequest is very slow with the progress bar

$appPath = Split-Path -Parent $PSScriptRoot
$runner  = Join-Path $PSScriptRoot 'run-service.cmd'

$services = @(
    @{
        Name        = 'qsu-fmas-queue'
        Display     = 'QSU-FMAS Queue Worker'
        Command     = 'queue:work'
        Log         = 'service-queue-worker'
        Description = 'Runs php artisan queue:work: backups, restores, push notifications, digitize hardcopy, notification bell.'
    },
    @{
        Name        = 'qsu-fmas-scheduler'
        Display     = 'QSU-FMAS Scheduler'
        Command     = 'schedule:work'
        Log         = 'service-scheduler'
        Description = 'Runs php artisan schedule:work: automatic backups, scan inbox, trash purge, cloud sync, log pruning.'
    }
)

# Created by the old scripts\install-queue-worker.ps1; removed so there are
# never two queue workers.
$legacyNames = @('LaravelQueueWorker')

function Assert-Admin {
    $principal = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Needs administrator rights - double-click install-services.bat instead.'
    }
}

function Get-Nssm {
    $installed = Join-Path $env:ProgramFiles 'nssm\nssm.exe'
    if (Test-Path $installed) { return $installed }

    $onPath = Get-Command nssm -ErrorAction SilentlyContinue
    if ($onPath) { return $onPath.Source }

    # The 2.24-101 build is the one NSSM recommends for Windows 10 and newer
    # (services made with 2.24 can fail to start there). The checksum is the
    # one in winget's NSSM.NSSM manifest, so the Internet Archive's copy is
    # just as safe - nssm.cc often answers "503 Service Unavailable".
    $urls = @(
        'https://nssm.cc/ci/nssm-2.24-101-g897c7ad.zip',
        'https://web.archive.org/web/2024id_/https://nssm.cc/ci/nssm-2.24-101-g897c7ad.zip'
    )
    $sha256 = '99F5045FFFBFFB745D67FE3A065A953C4A3D9C253B868892D9B685B0EE7D07B8'

    $tmp = Join-Path $env:TEMP ('nssm-' + [guid]::NewGuid())
    New-Item -ItemType Directory $tmp | Out-Null
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        $downloaded = $false
        foreach ($url in $urls) {
            foreach ($attempt in 1..3) {
                Write-Host "Downloading NSSM from $url ..."
                try {
                    Invoke-WebRequest $url -OutFile "$tmp\nssm.zip" -UseBasicParsing -TimeoutSec 120
                    $downloaded = $true
                    break
                } catch {
                    Write-Host "  failed: $($_.Exception.Message)"
                    Start-Sleep -Seconds 5
                }
            }
            if ($downloaded) { break }
        }
        if (-not $downloaded) {
            throw 'Could not download NSSM - check the internet connection and run this again.'
        }
        if ((Get-FileHash "$tmp\nssm.zip" -Algorithm SHA256).Hash -ne $sha256) {
            throw 'The downloaded NSSM does not match the expected checksum - not installing it.'
        }
        Expand-Archive "$tmp\nssm.zip" $tmp
        $exe = Get-ChildItem $tmp -Recurse -Filter nssm.exe | Where-Object { $_.Directory.Name -eq 'win64' } | Select-Object -First 1
        New-Item -ItemType Directory -Force (Split-Path $installed) | Out-Null
        Copy-Item $exe.FullName $installed
    } finally {
        Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
    }
    return $installed
}

function Invoke-Nssm {
    & $script:nssm @args
    if ($LASTEXITCODE -ne 0) { throw "nssm $args failed (exit code $LASTEXITCODE)" }
}

function Remove-ServiceIfPresent([string] $name) {
    $service = Get-Service $name -ErrorAction SilentlyContinue
    if (-not $service) { return }
    Write-Host "Removing service $name ..."

    # Stop-Service waits forever on a stuck service; give it 30 seconds,
    # then end NSSM and whatever it started.
    if ($service.Status -ne 'Stopped') {
        sc.exe stop $name | Out-Null
        try {
            $service.WaitForStatus('Stopped', [TimeSpan]::FromSeconds(30))
        } catch {
            $nssmPid = (Get-CimInstance Win32_Service -Filter "Name='$name'").ProcessId
            Write-Host "  not stopping - ending process $nssmPid"
            if ($nssmPid) { taskkill /PID $nssmPid /T /F | Out-Null }
        }
    }
    Invoke-Nssm remove $name confirm
}

Assert-Admin
$script:nssm = Get-Nssm

foreach ($name in ($services.Name + $legacyNames)) {
    Remove-ServiceIfPresent $name
}

if ($Uninstall) {
    Write-Host ''
    Write-Host 'Services removed. start-all.bat will open the Queue worker and Scheduler windows again.'
    return
}

$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) { $php = 'C:\xampp\php\php.exe' }
if (-not (Test-Path $php)) { throw "php.exe not found (looked on PATH and at $php)." }

# Web push needs OPENSSL_CONF in the real environment - see app\Support\OpensslConfig.php.
$serviceEnv = @("PHP_EXE=$php")
$opensslConf = Join-Path (Split-Path $php) 'extras\ssl\openssl.cnf'
if (Test-Path $opensslConf) {
    $serviceEnv += "OPENSSL_CONF=$opensslConf"
} else {
    Write-Warning "openssl.cnf not found at $opensslConf - push notifications will fail."
}

foreach ($s in $services) {
    Write-Host "Installing $($s.Display) ($($s.Name)) ..."
    $log = Join-Path $appPath "storage\logs\$($s.Log).log"

    Invoke-Nssm install $s.Name $runner $s.Command
    Invoke-Nssm set $s.Name DisplayName $s.Display
    Invoke-Nssm set $s.Name Description $s.Description
    Invoke-Nssm set $s.Name AppDirectory $appPath
    Invoke-Nssm set $s.Name AppEnvironmentExtra @serviceEnv
    Invoke-Nssm set $s.Name Start SERVICE_DELAYED_AUTO_START
    # run-service.cmd restarts artisan itself; this only covers the script
    # itself ending.
    Invoke-Nssm set $s.Name AppExit Default Restart
    Invoke-Nssm set $s.Name AppRestartDelay 10000
    Invoke-Nssm set $s.Name AppStdout $log
    Invoke-Nssm set $s.Name AppStderr $log
    # A new output file each time the service starts (this NSSM build
    # ignores AppRotateBytes); run-service.cmd deletes ones older than 14
    # days. Not AppRotateOnline: with it on, NSSM hung instead of
    # restarting or stopping once artisan exited.
    Invoke-Nssm set $s.Name AppRotateFiles 1

    Invoke-Nssm start $s.Name
}

Write-Host ''
Get-Service $services.Name | Format-Table Name, DisplayName, Status, StartType -AutoSize

# Leftover windows from start-all.bat would mean two schedulers running every
# task twice. The services' own php runs as nssm.exe -> cmd.exe -> php.exe.
$servicePids = (Get-CimInstance Win32_Service | Where-Object { $services.Name -contains $_.Name }).ProcessId
$procs = @{}
Get-CimInstance Win32_Process | ForEach-Object { $procs[[int] $_.ProcessId] = $_ }
$leftover = $procs.Values | Where-Object {
    $parent = $procs[[int] $_.ParentProcessId]
    $_.Name -eq 'php.exe' -and $_.CommandLine -match 'artisan\s+(queue|schedule):work' -and
        -not ($parent -and $servicePids -contains $parent.ParentProcessId)
}
if ($leftover) {
    Write-Warning 'A queue worker or scheduler is still running outside the services. Close the "Queue worker" and "Scheduler" windows.'
}

Write-Host "Output logs: $appPath\storage\logs\service-*.log"
Write-Host 'After changing code or .env: php artisan queue:restart (the scheduler picks changes up by itself).'
