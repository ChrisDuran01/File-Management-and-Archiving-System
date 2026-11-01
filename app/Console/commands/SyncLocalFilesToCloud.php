<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\FileVersion;
use App\Services\CloudFileUploader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Retries pushing files that only made it to local disk (their cloud
 * upload failed at the time - see CloudFileUploader) up to the cloud disk,
 * so the "will sync automatically once the connection recovers" message
 * shown on upload is actually true instead of a permanent local-only state.
 */
class SyncLocalFilesToCloud extends Command
{
    /**
     * Cap per run so a large backlog (e.g. after a long outage) can't turn
     * one scheduled run into an hours-long one - the rest picks up next run.
     */
    private const MAX_PER_RUN = 20;

    protected $signature = 'files:sync-to-cloud';

    protected $description = 'Retry uploading local-only files to the cloud disk';

    public function handle(CloudFileUploader $uploader): int
    {
        $this->syncVersions($uploader);

        $files = File::where('storage_type', 'local')->limit(self::MAX_PER_RUN)->get();

        if ($files->isEmpty()) {
            return self::SUCCESS;
        }

        $this->info("Found {$files->count()} local-only file(s) to retry.");

        $synced = 0;

        foreach ($files as $file) {
            if (! $file->local_path || ! Storage::disk('local')->exists($file->local_path)) {
                $this->warn("   -> skipped {$file->filename}: local copy is missing");
                continue;
            }

            $fullPath = Storage::disk('local')->path($file->local_path);

            if ($uploader->upload($file->filepath, $fullPath)) {
                $file->forceFill(['storage_type' => 'both'])->save();
                $synced++;
                $this->info("   -> synced {$file->filename}");
            } else {
                $this->warn("   -> still unreachable: {$file->filename}");
            }
        }

        $this->info("Synced {$synced} of {$files->count()} file(s).");

        return self::SUCCESS;
    }

    /** Older versions of a file (FileVersions) saved while the cloud was unreachable. */
    private function syncVersions(CloudFileUploader $uploader): void
    {
        $versions = FileVersion::where('storage_type', 'local')->whereNotNull('filepath')->limit(self::MAX_PER_RUN)->get();

        foreach ($versions as $version) {
            if (! $version->local_path || ! Storage::disk('local')->exists($version->local_path)) {
                $this->warn("   -> skipped {$version->filename} (v{$version->version_no}): local copy is missing");
                continue;
            }

            if ($uploader->upload($version->filepath, Storage::disk('local')->path($version->local_path))) {
                $version->forceFill(['storage_type' => 'both'])->save();
                $this->info("   -> synced {$version->filename} (v{$version->version_no})");
            } else {
                $this->warn("   -> still unreachable: {$version->filename} (v{$version->version_no})");
            }
        }
    }
}
