<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Archive ZIPs used to be written to the `public` disk, which the web
     * server hands out as /storage/archives/... to anyone holding the link -
     * no login, no restricted-folder check. They are now kept on the private
     * `local` disk (see Archive::localZipPath()), reachable only through
     * ArchiveController. This moves the ZIPs already on this server across;
     * no table changes.
     */
    public function up(): void
    {
        $this->moveZips(Storage::disk('public')->path('archives'), Storage::disk('local')->path('archives'));
    }

    public function down(): void
    {
        $this->moveZips(Storage::disk('local')->path('archives'), Storage::disk('public')->path('archives'));
    }

    private function moveZips(string $from, string $to): void
    {
        // The test suite migrates a blank database on every run - it must
        // not reach into this machine's real archive files while doing so.
        if (app()->runningUnitTests() || ! File::isDirectory($from)) {
            return;
        }

        File::ensureDirectoryExists($to);

        foreach (File::files($from) as $zip) {
            File::move($zip->getPathname(), $to . DIRECTORY_SEPARATOR . $zip->getFilename());
        }
    }
};
