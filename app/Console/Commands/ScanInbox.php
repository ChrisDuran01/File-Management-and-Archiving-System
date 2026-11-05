<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScanBatch;
use App\Models\ActivityLog;
use App\Models\ScanBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pulls newly scanned files out of the watched inbox folder
 * (config('scan.inbox_path') - where Epson Scan 2 / NAPS2 drops finished
 * PDFs) and turns each into a `scan_batches` row, then dispatches
 * ProcessScanBatch to OCR + propose splits. Bounded per run and safe to
 * run concurrently (mirrors SyncLocalFilesToCloud).
 */
class ScanInbox extends Command
{
    protected $signature = 'documents:scan-inbox {--limit= : Max files to ingest this run}';

    protected $description = 'Ingest newly scanned files from the watched inbox folder';

    public function handle(): int
    {
        $inbox      = rtrim((string) config('scan.inbox_path'), '/\\');
        $processing = $inbox . DIRECTORY_SEPARATOR . config('scan.processing_subdir');
        $processed  = $inbox . DIRECTORY_SEPARATOR . config('scan.processed_subdir');
        $failed     = $inbox . DIRECTORY_SEPARATOR . config('scan.failed_subdir');

        foreach ([$inbox, $processing, $processed, $failed] as $dir) {
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $this->error("Cannot create or access: {$dir}");
                return self::FAILURE;
            }
        }

        $limit = (int) ($this->option('limit') ?: config('scan.max_per_run'));
        $limit = max(1, min($limit, (int) config('scan.max_per_run')));

        $candidates = [];
        foreach ((array) config('scan.allowed_extensions') as $ext) {
            foreach (glob($inbox . DIRECTORY_SEPARATOR . '*.' . $ext) ?: [] as $path) {
                if (is_file($path)) {
                    $candidates[$path] = true;
                }
            }
        }
        $candidates = array_slice(array_keys($candidates), 0, $limit);

        if (! $candidates) {
            return self::SUCCESS;
        }

        $ingested = 0;

        foreach ($candidates as $path) {
            $name = basename($path);

            try {
                if (! $this->isStable($path)) {
                    $this->line("   -> skipped (still being written): {$name}");
                    continue;
                }

                // Atomic claim - also fails while the scanner still holds
                // the file open, which is exactly what we want.
                $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $claimed = $processing . DIRECTORY_SEPARATOR . time() . '_' . uniqid() . '.' . $ext;

                if (! @rename($path, $claimed)) {
                    $this->line("   -> skipped (claimed by another run): {$name}");
                    continue;
                }

                $checksum = hash_file('sha256', $claimed);

                $dupe = ScanBatch::where('checksum', $checksum)
                    ->where('status', '!=', 'failed')
                    ->exists();

                if ($dupe) {
                    @rename($claimed, $processed . DIRECTORY_SEPARATOR . $name);
                    Log::info("ScanInbox: duplicate of an existing batch, skipped: {$name}");
                    $this->line("   -> skipped (duplicate): {$name}");
                    continue;
                }

                $batch = ScanBatch::create([
                    'original_filename' => $name,
                    // 'local' (storage/app/private) - NOT the public disk.
                    // These are raw, possibly-restricted scanned documents;
                    // 'public' is symlinked straight to the web root, so
                    // anyone who guessed/enumerated a batch id could
                    // download it with no login at all. See ScanReviewController.
                    'stored_disk'       => 'local',
                    'mime_type'         => $this->mime($claimed),
                    'extension'         => $ext,
                    'checksum'          => $checksum,
                    'size'              => filesize($claimed) ?: null,
                    'source'            => 'inbox',
                    'status'            => 'ingesting',
                ]);

                $storedRel = "scan_batches/{$batch->id}/original.{$ext}";
                Storage::disk('local')->put($storedRel, file_get_contents($claimed));
                @unlink($claimed);

                $batch->update(['stored_path' => $storedRel, 'status' => 'extracting']);
                ProcessScanBatch::dispatch($batch->id);

                ActivityLog::create([
                    'user_name'  => 'System',
                    'activity'   => "Ingested scan batch #{$batch->id} ({$name})",
                    'ip_address' => null,
                ]);

                $ingested++;
                $this->info("   -> ingested batch #{$batch->id}: {$name}");
            } catch (\Throwable $e) {
                Log::error("ScanInbox: failed to ingest {$name}: " . $e->getMessage());

                // Move whatever is left (original or claimed copy) out of the
                // way so it isn't retried forever.
                foreach ([$path, ($claimed ?? null)] as $stray) {
                    if ($stray && is_file($stray)) {
                        @rename($stray, $failed . DIRECTORY_SEPARATOR . time() . '_' . $name);
                        break;
                    }
                }

                $this->error("   -> failed: {$name} ({$e->getMessage()})");
            }
        }

        $this->info("Ingested {$ingested} of " . count($candidates) . ' candidate file(s).');

        return self::SUCCESS;
    }

    /**
     * True once the file has stopped growing and is at least
     * config('scan.stable_seconds') old - so a scan still being written
     * isn't picked up half-complete.
     */
    private function isStable(string $path): bool
    {
        clearstatcache(true, $path);
        $size1 = @filesize($path);
        $mtime = @filemtime($path);

        if ($size1 === false || $mtime === false) {
            return false;
        }

        if (time() - $mtime < (int) config('scan.stable_seconds')) {
            return false;
        }

        usleep(250_000);
        clearstatcache(true, $path);

        if (@filesize($path) !== $size1) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $locked = @flock($handle, LOCK_SH | LOCK_NB);
        if ($locked) {
            @flock($handle, LOCK_UN);
        }
        @fclose($handle);

        return $locked;
    }

    private function mime(string $path): ?string
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            return $mime ?: null;
        }

        return null;
    }
}
