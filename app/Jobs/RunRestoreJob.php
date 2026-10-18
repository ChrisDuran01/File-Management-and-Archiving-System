<?php

namespace App\Jobs;

use App\Http\Controllers\BackupController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs an actual system restore out-of-band from the HTTP request that
 * triggers it (BackupController::restore()), same reasoning as
 * RunBackupJob: a restore re-imports the full database dump and re-uploads
 * every backed-up file to cloud storage, which can take just as long as
 * building the backup did in the first place - keeping it off the request
 * thread keeps the SuperAdmin page's progress polling responsive.
 *
 * Requires a queue worker (`php artisan queue:work`) actually running -
 * same operational dependency backups and the digitize-hardcopy pipeline
 * already have. Without one, this silently sits queued and never runs.
 */
class RunRestoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Matches performRestore()'s own set_time_limit(0).
    public $timeout = 0;

    // Never re-run on its own: a restore replaces the whole database, and
    // performRestore() already puts the old one back when a step fails.
    public $tries = 1;

    /**
     * @param int         $backupId      Which backups row to restore from.
     * @param string|null $initiatedBy   Who triggered this (for the activity
     *                                   log) - null for system-triggered runs.
     * @param string|null $initiatedByIp Same reasoning as above, for request()->ip().
     */
    public function __construct(
        private readonly int $backupId,
        private readonly ?string $initiatedBy = null,
        private readonly ?string $initiatedByIp = null,
    ) {
    }

    public function handle(): void
    {
        (new BackupController())->performRestore($this->backupId, $this->initiatedBy, $this->initiatedByIp);
    }
}
