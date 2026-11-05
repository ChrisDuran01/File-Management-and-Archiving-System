<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as FileFacade;

/**
 * Housekeeping for things nothing else ever deletes (found 2026-11-04):
 *
 * - Expired cache rows. The database cache store deletes an expired row
 *   only when the same key is read again, so keys that never come back -
 *   a visitor IP's rate limit, an old Reports version, a help-bot answer
 *   from before the guide changed - stay forever (215 of 219 rows).
 * - Half-written backup ZIPs. ZipArchive writes "backup_x.zip.a1b2c3" and
 *   renames it when done; a run that is killed leaves it behind (10.7 GB
 *   from 2026-03-21). A day old means no backup is still writing it.
 * - storage/app/temp. Archiving, restores, OCR and PDF tools clean up after
 *   themselves; what is left is from runs that crashed. A week old means
 *   nothing is still using it.
 *
 * Whole backups (*.zip) and pre-restore copies are never touched here -
 * BackupController::cleanupOldBackups() keeps those.
 */
class PruneLeftovers extends Command
{
    protected $signature = 'housekeeping:prune {--dry-run : Count what would be deleted without deleting anything}';

    protected $description = 'Delete expired cache rows, half-written backup ZIPs and old temp files';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $cacheRows = $this->pruneCache($dryRun);
        [$partials, $partialBytes] = $this->deleteOlderThan(glob(storage_path('app/backups/*.zip.*')) ?: [], now()->subDay()->getTimestamp(), $dryRun);
        [$temps, $tempBytes] = $this->deleteOlderThan($this->tempFiles(), now()->subWeek()->getTimestamp(), $dryRun);

        if (! $dryRun) {
            $this->removeEmptyTempFolders();
        }

        $mb = fn (int $bytes) => round($bytes / 1048576, 1) . ' MB';
        $this->info(($dryRun ? '[dry-run] Would delete ' : 'Deleted ')
            . "{$cacheRows} expired cache row(s), {$partials} half-written backup(s) ({$mb($partialBytes)}), {$temps} old temp file(s) ({$mb($tempBytes)}).");

        if (! $dryRun && ($partials || $temps)) {
            ActivityLog::create([
                'user_name'  => 'System',
                'activity'   => "Housekeeping removed {$partials} half-written backup(s) ({$mb($partialBytes)}) and {$temps} old temp file(s) ({$mb($tempBytes)})",
                'ip_address' => null,
            ]);
        }

        return self::SUCCESS;
    }

    private function pruneCache(bool $dryRun): int
    {
        $store = config('cache.stores.' . config('cache.default'));
        if (($store['driver'] ?? null) !== 'database') {
            return 0;
        }

        $rows  = DB::connection($store['connection'] ?? null)->table($store['table'] ?? 'cache')->where('expiration', '<=', time());
        $locks = DB::connection($store['lock_connection'] ?? $store['connection'] ?? null)->table($store['lock_table'] ?? 'cache_locks')->where('expiration', '<=', time());

        if ($dryRun) {
            return $rows->count();
        }

        $locks->delete();

        return $rows->delete();
    }

    /** @return array{0: int, 1: int} files deleted, bytes freed */
    private function deleteOlderThan(array $paths, int $cutoff, bool $dryRun): array
    {
        $count = 0;
        $bytes = 0;

        foreach ($paths as $path) {
            if (! is_file($path) || filemtime($path) >= $cutoff) {
                continue;
            }

            $size = filesize($path);
            if ($dryRun || @unlink($path)) {
                $count++;
                $bytes += $size;
            }
        }

        return [$count, $bytes];
    }

    private function tempFiles(): array
    {
        $dir = storage_path('app/temp');

        return is_dir($dir) ? array_map(fn ($file) => $file->getPathname(), FileFacade::allFiles($dir, true)) : [];
    }

    /** Folders left empty (an archive restore's extract_*), deepest first. */
    private function removeEmptyTempFolders(): void
    {
        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            return;
        }

        $entries = iterator_to_array(new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ));

        foreach ($entries as $path => $entry) {
            if ($entry->isDir()) {
                @rmdir($path); // only succeeds when it is empty
            }
        }
    }
}
