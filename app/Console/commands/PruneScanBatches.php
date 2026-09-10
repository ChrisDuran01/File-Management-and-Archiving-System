<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\ScanBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Housekeeping: drops the raw stored file and the row for scan batches
 * that were filed or failed longer ago than config('scan.batch_retention_days').
 * The filed `documents` / `files` are untouched - only the intermediate
 * batch artefacts go. Bounded per run.
 */
class PruneScanBatches extends Command
{
    private const MAX_PER_RUN = 50;

    protected $signature = 'documents:prune-scan-batches {--days= : Override the retention window}';

    protected $description = 'Delete old filed/failed scan batches and their stored raw files';

    public function handle(): int
    {
        $days   = (int) ($this->option('days') ?: config('scan.batch_retention_days'));
        $cutoff = now()->subDays(max(1, $days));

        $batches = ScanBatch::whereIn('status', ['filed', 'failed'])
            ->where('updated_at', '<', $cutoff)
            ->limit(self::MAX_PER_RUN)
            ->get();

        if ($batches->isEmpty()) {
            return self::SUCCESS;
        }

        $removed = 0;

        foreach ($batches as $batch) {
            // Clean up both disks - a no-op on whichever one this batch never
            // touched. Covers rows ingested before the pipeline moved off
            // the public disk (see ScanInbox), which still have artefacts there.
            Storage::disk('local')->deleteDirectory('scan_batches/' . $batch->id);
            Storage::disk('public')->deleteDirectory('scan_batches/' . $batch->id);
            $batch->delete();
            $removed++;
        }

        ActivityLog::create([
            'user_name'  => 'System',
            'activity'   => "Pruned {$removed} old scan batch(es) (older than {$days} day(s))",
            'ip_address' => null,
        ]);

        $this->info("Pruned {$removed} scan batch(es).");

        return self::SUCCESS;
    }
}
