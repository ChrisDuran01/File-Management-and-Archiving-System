<?php

namespace App\Services;

use App\Jobs\RunBackupJob;
use App\Models\Backup;
use App\Models\SiteSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Catch-up schedule for automatic backups.
 *
 * The old schedule fired at one fixed time (2:00 AM), which never happened
 * while this computer was off at night - there was no automatic backup
 * from 1 to 17 October 2026. Instead, the scheduler asks every few minutes
 * whether this period's backup (today's, this week's or this month's, per
 * the Backup page) exists yet, and queues one if not - so a backup runs
 * soon after the computer is switched on, whenever that is. A backup made
 * with "Backup now" counts too.
 */
class BackupScheduler
{
    /** After a scheduled backup is queued, wait this long before queuing another if it produced nothing. */
    public const RETRY_AFTER_HOURS = 3;

    private const ATTEMPT_KEY = 'backup:last-scheduled-attempt';

    /** Plain-language description of each frequency, for the dashboard. */
    public const DESCRIPTIONS = [
        'daily'   => 'Once a day, soon after the computer is on',
        'weekly'  => 'Once a week (Mon-Sun), soon after the computer is on',
        'monthly' => 'Once a month, soon after the computer is on',
    ];

    /** Queues a backup if one is due. Returns whether it did. */
    public function runIfDue(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        if (! $this->isDue($now)) {
            return false;
        }

        Cache::put(self::ATTEMPT_KEY, $now->getTimestamp(), $now->copy()->addDays(40));
        Log::info('Scheduled backup queued - no ' . (SiteSetting::current()->backup_frequency ?? 'daily') . ' backup yet this period.');

        // Both null: nobody triggered this - see RunBackupJob.
        RunBackupJob::dispatch();

        return true;
    }

    public function isDue(CarbonInterface $now): bool
    {
        $settings = SiteSetting::current();

        if (! ($settings->backup_enabled ?? true)) {
            return false;
        }

        $periodStart = self::periodStart($settings->backup_frequency ?? 'daily', $now);

        $lastBackup = Backup::max('created_at');
        if ($lastBackup && Carbon::parse($lastBackup)->gte($periodStart)) {
            return false;
        }

        // A failed run leaves no backup row behind; without this it would be
        // queued again (and e-mail its failure again) every few minutes.
        $lastAttempt = Cache::get(self::ATTEMPT_KEY);
        if ($lastAttempt && $now->getTimestamp() - $lastAttempt < self::RETRY_AFTER_HOURS * 3600) {
            return false;
        }

        return true;
    }

    /** Start of the day, week (Monday) or month a backup is due for. */
    public static function periodStart(string $frequency, CarbonInterface $now): CarbonInterface
    {
        return match ($frequency) {
            'weekly'  => $now->copy()->startOfWeek(CarbonInterface::MONDAY),
            'monthly' => $now->copy()->startOfMonth(),
            default   => $now->copy()->startOfDay(),
        };
    }
}
