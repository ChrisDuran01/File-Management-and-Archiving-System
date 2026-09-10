<?php

namespace App\Jobs;

use App\Http\Controllers\BackupController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the actual backup out-of-band from the HTTP request that triggers
 * it (BackupController::createBackup(), and the scheduled run in
 * routes/console.php). A backup can take anywhere from a couple minutes to
 * well over an hour - large buckets, and Supabase uploads have been seen to
 * time out and retry for 10+ minutes each. Running that inline in the
 * request meant the SuperAdmin backup page's own progress-polling requests
 * had to compete with it for a PHP worker - on `php artisan serve`
 * (single-threaded) they couldn't be served AT ALL until the backup
 * finished, so the progress bar sat frozen the whole time, then a burst of
 * queued-up poll requests all resolved to "done" at once, each one
 * independently reloading the page. Running the backup on the queue instead
 * means the web server is always free to answer /backup/progress
 * immediately, on any server, threaded or not.
 *
 * Requires a queue worker (`php artisan queue:work`) actually running -
 * same operational dependency the digitize-hardcopy pipeline already has.
 * Without one, this silently sits queued and never runs.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Matches performBackup()'s own set_time_limit(0) - a backup can
    // legitimately run for well over an hour (see class docblock), so the
    // queue worker must not kill it as "stuck".
    public $timeout = 0;

    /**
     * @param string|null $initiatedBy   Who triggered this (for the activity
     *                                   log) - null for scheduled/system runs,
     *                                   since there's no Auth::user() to ask
     *                                   once this is running on the queue.
     * @param string|null $initiatedByIp Same reasoning as above, for request()->ip().
     */
    public function __construct(
        private readonly ?string $initiatedBy = null,
        private readonly ?string $initiatedByIp = null,
    ) {
    }

    public function handle(): void
    {
        (new BackupController())->performBackup($this->initiatedBy, $this->initiatedByIp);
    }
}
