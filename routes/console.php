<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BackupController;

// TEST ONLY - Remove this after testing
Schedule::call(function () {
    Log::info('TEST: Scheduler is running every minute');
})->everyMinute();

// Retention policy: move folders stored under a past school year into the
// ZIP archive once they haven't been opened in a long time
Schedule::command('folders:archive-stale')->dailyAt('03:00');

// Retry files whose cloud upload failed at the time (Supabase has shown
// repeated intermittent timeouts - see storage/logs/laravel.log) and were
// saved local-only instead - see CloudFileUploader.
Schedule::command('files:sync-to-cloud')->everyFifteenMinutes()->withoutOverlapping();

// Your actual backup schedule
$frequency = session('backup_frequency', 'daily');
$backupEnabled = session('backup_enabled', true);

if ($backupEnabled) {
    $backupTask = Schedule::call(function () {
        try {
            Log::info('Backup attempt started - Frequency: ' . session('backup_frequency', 'daily'));
            $backupController = new BackupController();
            $backupController->createBackup();
            Log::info('Backup attempt completed successfully');
        } catch (\Exception $e) {
            Log::error('Backup error: ' . $e->getMessage());
        }
    });
    
    switch ($frequency) {
        case 'daily':
            $backupTask->dailyAt('02:00');
            Log::info('Backup scheduler: DAILY at 2:00 AM');
            break;
        case 'weekly':
            $backupTask->weekly()->mondays()->at('02:00');
            Log::info('Backup scheduler: WEEKLY on Mondays at 2:00 AM');
            break;
        case 'monthly':
            $backupTask->monthlyOn(1, '02:00');
            Log::info('Backup scheduler: MONTHLY on the 1st at 2:00 AM');
            break;
    }
}