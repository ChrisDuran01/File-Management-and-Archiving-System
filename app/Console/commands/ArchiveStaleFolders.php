<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Services\FolderArchiver;
use Illuminate\Console\Command;

class ArchiveStaleFolders extends Command
{
    /**
     * Default number of years a folder can sit under a past school year,
     * untouched, before it's eligible for the ZIP archive.
     */
    private const DEFAULT_STALE_YEARS = 3;

    protected $signature = 'folders:archive-stale {--years=} {--dry-run : List what would be archived without actually archiving it}';

    protected $description = 'Move folders stored under a past school year into the ZIP archive once they have not been opened in a long time';

    public function handle(FolderArchiver $archiver): int
    {
        $yearsOption = $this->option('years');
        $years       = ($yearsOption === null || $yearsOption === '') ? self::DEFAULT_STALE_YEARS : (int) $yearsOption;
        $dryRun  = (bool) $this->option('dry-run');
        $cutoff  = now()->subYears($years);

        $candidates = Folder::where('is_archived', true)
            ->whereNotNull('school_year')
            ->get()
            ->filter(function (Folder $folder) use ($cutoff) {
                $lastUsed = $folder->last_accessed_at ?? $folder->updated_at ?? $folder->created_at;
                return $lastUsed->lt($cutoff) && File::where('folder_id', $folder->id)->exists();
            });

        if ($candidates->isEmpty()) {
            $this->info("No folders inactive for {$years}+ year(s) were found.");
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$candidates->count()} folder(s) inactive for {$years}+ year(s):");

        foreach ($candidates as $folder) {
            $lastUsed = $folder->last_accessed_at ?? $folder->updated_at ?? $folder->created_at;
            $this->line(" - {$folder->name} (SY {$folder->school_year}, last opened {$lastUsed->diffForHumans()})");

            if ($dryRun) {
                continue;
            }

            $remarks = "Auto-archived by the retention policy: inactive for over {$years} year(s) under SY {$folder->school_year}.";
            $result  = $archiver->archive($folder, 'System (Retention Policy)', 'auto_retention', $remarks);

            if ($result['success']) {
                ActivityLog::create([
                    'user_name'  => 'System',
                    'activity'   => 'Auto-archived stale folder: ' . $folder->name . ' (SY ' . $folder->school_year . ')',
                    'ip_address' => null,
                ]);
                $this->info("   -> archived ({$result['archivedFilesCount']} files)");
            } else {
                $this->warn('   -> skipped: ' . $result['message']);
            }
        }

        return self::SUCCESS;
    }
}
