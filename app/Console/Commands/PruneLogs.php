<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retention: activity_logs and help_bot_logs hold personal data (names, IPs,
 * question text), and engagement_events (anonymous, but still student
 * behaviour) would otherwise grow forever. Deletes rows older than
 * config('retention.*'). Deletes in bounded batches so a big first run
 * doesn't hold a long table lock.
 */
class PruneLogs extends Command
{
    private const BATCH = 5000;

    protected $signature = 'logs:prune {--dry-run : Count what would be deleted without deleting anything}';

    protected $description = 'Delete activity-log, help-bot-log and engagement-event rows older than their retention window';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $targets = [
            'activity_logs'     => (int) config('retention.activity_log_days'),
            'help_bot_logs'     => (int) config('retention.help_bot_log_days'),
            'engagement_events' => (int) config('retention.engagement_event_days'),
        ];

        $summary = [];

        foreach ($targets as $table => $days) {
            // A non-positive value would delete everything - treat it as
            // "not configured" and skip instead.
            if ($days < 1) {
                $this->warn("{$table}: retention days not set (>= 1 required), skipped.");
                continue;
            }

            $cutoff  = now()->subDays($days);
            $deleted = 0;

            if ($dryRun) {
                $deleted = DB::table($table)->where('created_at', '<', $cutoff)->count();
            } else {
                do {
                    $batch = DB::table($table)
                        ->where('created_at', '<', $cutoff)
                        ->limit(self::BATCH)
                        ->delete();
                    $deleted += $batch;
                } while ($batch === self::BATCH);
            }

            $this->info(($dryRun ? '[dry-run] Would delete ' : 'Deleted ') . "{$deleted} row(s) from {$table} older than {$days} days.");

            if ($deleted > 0) {
                $summary[] = "{$deleted} from {$table}";
            }
        }

        if ($summary && ! $dryRun) {
            ActivityLog::create([
                'user_name'  => 'System',
                'activity'   => 'Log retention purge removed ' . implode(', ', $summary),
                'ip_address' => null,
            ]);
        }

        return self::SUCCESS;
    }
}
