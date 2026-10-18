<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Backup;
use App\Services\FileResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * One-time move of the uploaded files from Supabase to Backblaze B2
 * (October 2026 - see config/filesystems.php):
 *
 *  - every object in the Supabase bucket except backups/ is copied to the
 *    'b2_files' disk at the same path, stored with its type, then read back
 *    and compared by SHA-256;
 *  - the old backups still in Supabase go to the 'backups' disk, checked
 *    against the checksum recorded when each was made, and their rows are
 *    pointed there.
 *
 * Nothing is deleted from Supabase. Safe to run again: what is already
 * there (same size) is skipped. Without --apply it only reports.
 */
class MoveFilesToBackblaze extends Command
{
    protected $signature = 'files:move-to-backblaze {--apply : Copy for real (without it, nothing is changed)}';

    protected $description = 'Copy every uploaded file (and old backups) from Supabase to Backblaze B2, checking each one';

    private array $supabase;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->supabase = config('filesystems.disks.supabase');
        $b2 = Storage::disk('b2_files');

        if (! config('filesystems.disks.b2_files.bucket') || ! config('filesystems.disks.b2_files.key')) {
            $this->error('FILES_B2_KEY_ID, FILES_B2_APPLICATION_KEY and FILES_B2_BUCKET must be set in .env first.');
            return self::FAILURE;
        }

        $this->info('Listing the Supabase bucket…');
        $objects = $this->listSupabase('');
        if ($objects === null) {
            $this->error('Could not list the Supabase bucket - nothing was copied.');
            return self::FAILURE;
        }

        $files = array_filter($objects, fn ($o) => ! str_starts_with($o['name'], 'backups/'));
        $this->line(count($files) . ' files to move (' . $this->mb(array_sum(array_column($files, 'size'))) . '), backups/ handled separately.');

        $copied = $skipped = $failed = 0;
        $bar = $this->output->createProgressBar(count($files));

        foreach ($files as $object) {
            $path = $object['name'];

            if ($b2->exists($path) && $b2->size($path) === $object['size']) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if (! $apply) {
                $copied++;
                $bar->advance();
                continue;
            }

            $error = $this->copyOne($path, $b2);
            if ($error === null) {
                $copied++;
            } else {
                $failed++;
                $this->newLine();
                $this->warn("  {$path}: {$error}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->line(sprintf('Files: %d %s, %d already there, %d failed.', $copied, $apply ? 'copied and checked' : 'would be copied', $skipped, $failed));

        [$backupsMoved, $backupsFailed] = $this->moveOldBackups($apply);

        if (! $apply) {
            $this->info('Dry run - nothing was changed. Run with --apply to copy.');
            return self::SUCCESS;
        }

        ActivityLog::create([
            'user_name'  => 'System',
            'activity'   => "Copied files from Supabase to Backblaze: {$copied} copied and checked, {$skipped} already there, {$failed} failed; {$backupsMoved} old backups moved",
            'ip_address' => null,
        ]);

        if ($failed || $backupsFailed) {
            $this->error('Some items failed - run the command again before switching FILES_CLOUD to b2.');
            return self::FAILURE;
        }

        $this->info('Everything is in Backblaze. Next: set FILES_CLOUD=b2 in .env and restart the queue worker.');
        return self::SUCCESS;
    }

    /**
     * Copies one file and reads it back to compare, trying up to 3 times -
     * Supabase downloads from here drop or stall now and then. Null on
     * success, else what went wrong the last time.
     */
    private function copyOne(string $path, $b2): ?string
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $error = $this->copyOnce($path, $b2);
            if ($error === null) {
                return null;
            }
            if ($attempt < 3) {
                sleep(3);
            }
        }

        return $error;
    }

    private function copyOnce(string $path, $b2): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'move_');

        try {
            $response = Http::timeout(600)->withHeaders($this->supabaseAuth())->sink($tmp)->get($this->supabaseObjectUrl($path));
            if (! $response->successful()) {
                return 'could not download from Supabase (HTTP ' . $response->status() . ')';
            }

            $hash = hash_file('sha256', $tmp);
            $stream = fopen($tmp, 'r');
            $stored = $b2->writeStream($path, $stream, [
                'ContentType' => FileResponse::contentTypeFor($path),
                'mimetype'    => FileResponse::contentTypeFor($path),
            ]);
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (! $stored) {
                return 'Backblaze refused the upload';
            }

            $back = $b2->readStream($path);
            if (! is_resource($back)) {
                // Uploaded but not checked: remove it, so a later run copies
                // it again instead of skipping it as "already there".
                $b2->delete($path);
                return 'could not read the copy back from Backblaze to check it';
            }
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, $back);
            fclose($back);

            if (hash_equals($hash, hash_final($ctx))) {
                return null;
            }
            $b2->delete($path); // a copy that doesn't match must not be skipped next time

            return 'the copy in Backblaze does not match - try again';
        } catch (\Throwable $e) {
            return $e->getMessage();
        } finally {
            @unlink($tmp);
        }
    }

    /** Old backups whose off-site copy is still in Supabase go to the backups bucket. */
    private function moveOldBackups(bool $apply): array
    {
        $backups = Backup::where('cloud_disk', 'cloud')->whereNotNull('cloud_path')->get();
        if ($backups->isEmpty()) {
            return [0, 0];
        }

        $this->line($backups->count() . ' old backups in Supabase ' . ($apply ? 'moving' : 'would move') . ' to the backups bucket.');
        if (! $apply) {
            return [0, 0];
        }

        $moved = $failed = 0;
        $disk = Storage::disk('backups');

        foreach ($backups as $backup) {
            $tmp = tempnam(sys_get_temp_dir(), 'movebk_');

            try {
                // Up to 3 tries - Supabase downloads from here drop now and then.
                $response = Http::timeout(1200)->retry(3, 3000, throw: false)
                    ->withHeaders($this->supabaseAuth())->sink($tmp)->get($this->supabaseObjectUrl($backup->cloud_path));

                $ok = $response->successful()
                    && (! $backup->checksum || hash_equals($backup->checksum, hash_file('sha256', $tmp)));

                if ($ok) {
                    $stream = fopen($tmp, 'r');
                    $ok = $disk->writeStream($backup->cloud_path, $stream) && $disk->size($backup->cloud_path) === filesize($tmp);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                if ($ok) {
                    $backup->update(['cloud_disk' => 'backups']);
                    $moved++;
                    $this->line("  moved {$backup->name}");
                } else {
                    $failed++;
                    $this->warn("  {$backup->name}: could not be moved and checked - its record still points to Supabase");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  {$backup->name}: " . $e->getMessage());
            } finally {
                @unlink($tmp);
            }
        }

        return [$moved, $failed];
    }

    /**
     * Every object under $prefix, recursively, as [name, size] - through
     * Supabase's REST API, since its Flysystem adapter can't page past 100.
     */
    private function listSupabase(string $prefix): ?array
    {
        $all = [];
        $limit = 1000;
        $offset = 0;
        $url = rtrim((string) $this->supabase['endpoint'], '/') . '/storage/v1/object/list/' . $this->supabase['bucket'];

        do {
            $response = Http::timeout(60)->withHeaders($this->supabaseAuth())->post($url, ['limit' => $limit, 'offset' => $offset, 'prefix' => $prefix]);
            if (! $response->successful()) {
                return null;
            }

            $page = $response->json();
            foreach ($page as $item) {
                $name = $item['name'] ?? null;
                if (! $name || $name === '.') {
                    continue;
                }

                if (($item['id'] ?? null) === null) { // a folder placeholder - go inside
                    $nested = $this->listSupabase($prefix . $name . '/');
                    if ($nested === null) {
                        return null;
                    }
                    $all = array_merge($all, $nested);
                    continue;
                }

                $all[] = ['name' => $prefix . $name, 'size' => (int) ($item['metadata']['size'] ?? 0)];
            }

            $offset += $limit;
        } while (count($page) === $limit);

        return $all;
    }

    private function supabaseObjectUrl(string $path): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return rtrim((string) $this->supabase['endpoint'], '/') . '/storage/v1/object/' . $this->supabase['bucket'] . '/' . $encoded;
    }

    private function supabaseAuth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->supabase['key'], 'apikey' => $this->supabase['key']];
    }

    private function mb(int $bytes): string
    {
        return round($bytes / 1048576, 1) . ' MB';
    }
}
