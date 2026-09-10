<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Services\TrashPurger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Permanently removes items that have sat in the Trash longer than the
 * retention window (config('trash.retention_days')), so the recycle bin
 * doesn't grow forever. Bounded per run like the other scheduled commands.
 */
class PurgeTrash extends Command
{
    private const MAX_PER_RUN = 100;

    protected $signature = 'trash:purge-expired {--dry-run : List what would be purged without deleting anything}';

    protected $description = 'Permanently delete Trash items older than the retention window';

    public function handle(TrashPurger $purger): int
    {
        $cutoff = now()->subDays((int) config('trash.retention_days'));
        $dryRun = (bool) $this->option('dry-run');
        $purged = 0;

        // Folders first: purging a folder also purges its files, which keeps
        // the file pass below from double-handling them.
        $folders = Folder::onlyTrashed()->where('deleted_at', '<', $cutoff)->limit(self::MAX_PER_RUN)->get();

        foreach ($folders as $folder) {
            $this->line("   -> folder: {$folder->name}");
            if (! $dryRun) {
                $purger->purgeFolder($folder);
            }
            $purged++;
        }

        $files = File::onlyTrashed()->where('deleted_at', '<', $cutoff)->limit(self::MAX_PER_RUN)->get();

        foreach ($files as $file) {
            $this->line("   -> file: {$file->filename}");
            if (! $dryRun) {
                $purger->purgeFile($file);
            }
            $purged++;
        }

        $documents = Document::onlyTrashed()->where('deleted_at', '<', $cutoff)->limit(self::MAX_PER_RUN)->get();

        foreach ($documents as $document) {
            $this->line("   -> document: {$document->title}");
            if (! $dryRun) {
                $purger->purgeDocument($document);
            }
            $purged++;
        }

        if ($purged > 0 && ! $dryRun) {
            Log::info("trash:purge-expired removed {$purged} item(s) older than " . config('trash.retention_days') . ' days.');

            ActivityLog::create([
                'user_name'  => 'System',
                'activity'   => "Trash auto-purge removed {$purged} item(s) older than " . config('trash.retention_days') . ' days',
                'ip_address' => null,
            ]);
        }

        $this->info(($dryRun ? '[dry-run] Would purge ' : 'Purged ') . $purged . ' item(s).');

        return self::SUCCESS;
    }
}
