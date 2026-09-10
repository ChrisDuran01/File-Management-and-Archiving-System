<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Jobs\RunBackupJob;
use App\Models\SiteSetting;

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

// Digitize hardcopy: pull newly scanned files out of the watched inbox and
// kick off split/OCR processing for each (ScanInbox + ProcessScanBatch).
Schedule::command('documents:scan-inbox')->everyFiveMinutes()->withoutOverlapping();

// Housekeeping: drop raw scan-batch files that were filed or abandoned
// longer ago than scan.batch_retention_days.
Schedule::command('documents:prune-scan-batches')->dailyAt('03:30');

// Recycle bin: permanently remove items that have sat in the Trash longer
// than trash.retention_days.
Schedule::command('trash:purge-expired')->dailyAt('03:45');

// Notifications: drop read bell entries older than 60 days so the table
// doesn't grow forever. Unread ones are kept regardless of age.
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('notifications')
        ->whereNotNull('read_at')
        ->where('created_at', '<', now()->subDays(60))
        ->delete();
})->dailyAt('04:00');

// Your actual backup schedule
//
// Read from the persisted site_settings row, not session() - this file runs
// in the scheduler's own process (`php artisan schedule:run`, invoked by
// cron/Task Scheduler with no browser session at all), which can never see
// data tied to a browser's session cookie. session('backup_frequency', ...)
// here always silently returned the default, so the SuperAdmin frequency
// dropdown and enable/disable toggle never actually affected the real
// schedule - it always ran as if frequency=daily and enabled=true, no
// matter what was picked in the UI.
//
// Guarded: this file is evaluated on EVERY artisan command, migrate
// included - must not break a fresh install before site_settings exists.
try {
    $backupSettings = Schema::hasTable('site_settings') ? SiteSetting::current() : null;
    $frequency      = $backupSettings->backup_frequency ?? 'daily';
    $backupEnabled  = $backupSettings->backup_enabled ?? true;
} catch (\Throwable $e) {
    $frequency     = 'daily';
    $backupEnabled = true;
}

if ($backupEnabled) {
    $backupTask = Schedule::call(function () use ($frequency) {
        try {
            Log::info('Backup attempt queued - Frequency: ' . $frequency);
            // Dispatched, not run inline - see RunBackupJob's docblock. Both
            // null: nobody triggered this, there's nothing to attribute it to.
            RunBackupJob::dispatch();
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