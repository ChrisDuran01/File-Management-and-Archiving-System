<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Same move as the archive ZIPs (previous migration), for the rest of
     * what officers upload: every file's local copy (uploads/), and the scan
     * batches ingested before that pipeline switched disks. On the `public`
     * disk each of those opens by a plain /storage/... link for anyone
     * holding it - no login, no restricted-folder check - and a scan batch's
     * link can simply be guessed (scan_batches/<id>/original.pdf).
     *
     * Relative paths are kept, so files.local_path and
     * scan_batches.stored_path stay valid as they are; only the disk they
     * are read from changes (`local`, storage/app/private).
     */
    public function up(): void
    {
        $this->moveTree('uploads', 'public', 'local');
        $this->moveTree('scan_batches', 'public', 'local');

        DB::table('scan_batches')->where('stored_disk', 'public')->update(['stored_disk' => 'local']);
    }

    /**
     * Uploads go back. Scan batches stay private: their rows now say
     * `local`, and nothing records which of them used to be `public`.
     */
    public function down(): void
    {
        $this->moveTree('uploads', 'local', 'public');
    }

    private function moveTree(string $directory, string $fromDisk, string $toDisk): void
    {
        $from = Storage::disk($fromDisk)->path($directory);

        // The test suite migrates a blank database on every run - it must
        // not reach into this machine's real files while doing so.
        if (app()->runningUnitTests() || ! File::isDirectory($from)) {
            return;
        }

        $to = Storage::disk($toDisk)->path($directory);

        foreach (File::allFiles($from, true) as $file) {
            $target = $to . DIRECTORY_SEPARATOR . $file->getRelativePathname();

            File::ensureDirectoryExists(dirname($target));

            // Stop rather than carry on with a file left behind - the code
            // only looks on the new disk from here on. Safe to run again:
            // whatever already moved is simply no longer in $from.
            if (! File::move($file->getPathname(), $target)) {
                throw new RuntimeException("Could not move {$file->getPathname()} to {$target}.");
            }
        }

        File::deleteDirectory($from);
    }
};
