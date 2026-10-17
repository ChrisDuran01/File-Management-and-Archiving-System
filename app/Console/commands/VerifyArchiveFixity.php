<?php

namespace App\Console\Commands;

use App\Models\Archive;
use App\Models\User;
use App\Notifications\ArchiveFixityFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Re-checks every un-restored archive's ZIP against the SHA-256 recorded
 * when it was created (FolderArchiver::archive()). Without this, that
 * checksum is only ever compared reactively - at restore time - so an
 * archive that's never restored could sit silently corrupted for years.
 * This surfaces that on its own schedule instead (see routes/console.php).
 */
class VerifyArchiveFixity extends Command
{
    protected $signature = 'archives:verify-fixity';

    protected $description = 'Re-verify every archived ZIP against its recorded checksum, and alert SuperAdmins if one no longer matches';

    public function handle(): int
    {
        // Archives with no recorded checksum are included on purpose: they
        // can't be verified, so matchesChecksum() fails them and they get
        // flagged instead of silently passing.
        $archives = Archive::whereNull('restored_at')->get();

        if ($archives->isEmpty()) {
            $this->info('No archives to verify.');
            return self::SUCCESS;
        }

        $this->info("Verifying {$archives->count()} archive(s)...");

        $checked = 0;
        $failed  = 0;
        $missing = 0;

        foreach ($archives as $archive) {
            $zipContent = $this->readArchiveBytes($archive);

            if ($zipContent === null) {
                // Not the same as a fixity failure - the checksum can't be
                // compared against bytes that aren't there at all. Left
                // unflagged (not 'ok', not 'failed') so it's distinguishable
                // from a real integrity failure in the archives list.
                $this->warn(" - {$archive->folder_name}: ZIP not found in local or cloud storage");
                $missing++;
                continue;
            }

            $ok = $archive->matchesChecksum($zipContent);
            $archive->update([
                'fixity_checked_at' => now(),
                'fixity_status'     => $ok ? 'ok' : 'failed',
            ]);
            $checked++;

            if (! $ok) {
                $failed++;
                $this->error(" - {$archive->folder_name}: FAILED checksum verification");
                Notification::send(User::superAdmins(), new ArchiveFixityFailed($archive));
            }
        }

        $this->info("Done: {$checked} checked, {$failed} failed, {$missing} missing.");

        return self::SUCCESS;
    }

    private function readArchiveBytes(Archive $archive): ?string
    {
        $localPath = $archive->localZipPath();

        if (file_exists($localPath)) {
            return file_get_contents($localPath);
        }

        if ($archive->file_path && Storage::disk('cloud')->exists($archive->file_path)) {
            try {
                return Storage::disk('cloud')->get($archive->file_path);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
