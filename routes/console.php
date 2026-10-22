<?php

use Illuminate\Support\Facades\Schedule;
use App\Services\BackupScheduler;

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

// Fixity: re-check every un-restored archive's ZIP against its recorded
// checksum, so silent corruption surfaces on its own instead of only being
// discovered the day someone actually needs to restore it. Weekly, not
// daily - each run reads every archive's full bytes to hash them.
Schedule::command('archives:verify-fixity')->weeklyOn(0, '04:15');

// Notifications: drop read bell entries older than 60 days so the table
// doesn't grow forever. Unread ones are kept regardless of age.
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('notifications')
        ->whereNotNull('read_at')
        ->where('created_at', '<', now()->subDays(60))
        ->delete();
})->dailyAt('04:00');

// Retention: personal data in activity_logs / help_bot_logs is only kept
// for retention.activity_log_days / retention.help_bot_log_days.
Schedule::command('logs:prune')->dailyAt('04:30');

// Automatic backups: every 15 minutes, check whether this day's / week's /
// month's backup (per the Backup page) exists yet, and queue one if not -
// see BackupScheduler. A fixed 2:00 AM run never happened while this
// computer was off at night.
//
// The settings are read inside the task, not when this file loads: this
// file is evaluated on EVERY artisan command, and reading the database
// here made every command hang whenever MySQL did.
//
// Not an arrow function on purpose: the scheduler counts a task that
// returns false as failed, and runIfDue() returns false whenever no
// backup is due - most of the time.
Schedule::call(function () {
    app(BackupScheduler::class)->runIfDue();
})
    ->everyFifteenMinutes()
    ->name('backup-catch-up')
    ->withoutOverlapping();