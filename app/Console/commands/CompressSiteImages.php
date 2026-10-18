<?php

namespace App\Console\Commands;

use App\Http\Controllers\SiteSettingController;
use App\Models\ActivityLog;
use App\Services\ImageCompressor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One-time shrink of the site images uploaded before ImageCompressor
 * existed. Only images the database points at are touched; each original
 * is copied to private storage (image-originals/<date>/...) before it is
 * replaced. Without --apply it only reports what it would do.
 */
class CompressSiteImages extends Command
{
    protected $signature = 'images:compress-existing {--apply : Replace the images (without it, nothing is changed)}';

    protected $description = 'Shrink site images uploaded before compression existed, keeping the originals';

    /** A re-save has to save at least this much to be worth replacing a file that has no metadata to strip. */
    private const MIN_SAVING = 0.10;

    public function handle(ImageCompressor $compressor): int
    {
        $apply      = (bool) $this->option('apply');
        $public     = Storage::disk('public');
        $backupRoot = 'image-originals/' . now()->format('Y-m-d');

        $rows = [];
        $before = $after = 0;
        $changed = 0;

        foreach ($this->references() as [$table, $column, $maxEdge, $nameColumn]) {
            $paths = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->pluck($column)->unique();

            foreach ($paths as $path) {
                if (! $public->exists($path)) {
                    $rows[] = [$path, '-', '-', 'missing - skipped'];
                    continue;
                }

                $fullPath = $public->path($path);
                $size     = filesize($fullPath);
                $result   = $compressor->compress($fullPath, $maxEdge);

                if ($result === null || ! $this->worthReplacing($fullPath, $size, strlen($result[0]))) {
                    $rows[] = [$path, $this->kb($size), $this->kb($size), 'already fine'];
                    continue;
                }

                [$bytes, $extension] = $result;
                $newPath = $this->newPath($path, $extension);

                $rows[] = [$path, $this->kb($size), $this->kb(strlen($bytes)), $newPath === $path ? 'shrunk' : 'shrunk, now .' . $extension];
                $before += $size;
                $after  += strlen($bytes);
                $changed++;

                if (! $apply) {
                    continue;
                }

                $backup = $backupRoot . '/' . $path;
                if (! Storage::disk('local')->exists($backup)) {
                    Storage::disk('local')->put($backup, $public->get($path));
                }

                $public->put($newPath, $bytes);

                if ($newPath !== $path) {
                    $update = [$column => $newPath];
                    if ($nameColumn) {
                        $update[$nameColumn] = DB::raw($this->renamedOriginalName($nameColumn, $extension));
                    }
                    DB::table($table)->where($column, $path)->update($update);
                    $public->delete($path);
                }
            }
        }

        $this->table(['Image', 'Before', 'After', 'What happens'], $rows);
        $this->line(sprintf('%d of %d images %s: %s -> %s', $changed, count($rows), $apply ? 'shrunk' : 'would be shrunk', $this->kb($before), $this->kb($after)));

        if (! $apply) {
            $this->info('Dry run - nothing was changed. Run with --apply to do it.');
            return self::SUCCESS;
        }

        if ($changed > 0) {
            ActivityLog::create([
                'user_name'  => 'System',
                'activity'   => "Shrunk {$changed} site images ({$this->kb($before)} -> {$this->kb($after)}); originals kept in storage/app/private/{$backupRoot}",
                'ip_address' => null,
            ]);
            $this->info("Originals kept in storage/app/private/{$backupRoot}");
        }

        return self::SUCCESS;
    }

    /** [table, column, longest edge, column with the download name to follow the new type] */
    private function references(): array
    {
        $references = [
            ['announcement_images', 'path', ImageCompressor::ANNOUNCEMENT_IMAGE, null],
            ['announcements', 'attachment_path', ImageCompressor::ANNOUNCEMENT_ATTACHMENT, 'attachment_original_name'],
            ['users', 'profile_photo', ImageCompressor::PHOTO, null],
            ['officer_terms', 'site_photo', ImageCompressor::PHOTO, null],
            ['display_officers', 'photo', ImageCompressor::PHOTO, null],
        ];

        foreach (SiteSettingController::IMAGE_SLOT_SIZES as $column => $maxEdge) {
            $references[] = ['site_settings', $column, $maxEdge, null];
        }

        return $references;
    }

    /** Replace when it saves real space - or always for a JPEG carrying metadata (location). */
    private function worthReplacing(string $fullPath, int $size, int $newSize): bool
    {
        $head = (string) file_get_contents($fullPath, false, null, 0, 65536);
        $hasExif = str_starts_with($head, "\xFF\xD8") && str_contains($head, "Exif\x00\x00");

        return $hasExif || $newSize <= $size * (1 - self::MIN_SAVING);
    }

    private function newPath(string $path, string $extension): string
    {
        $info = pathinfo($path);
        if (strtolower($info['extension'] ?? '') === $extension) {
            return $path;
        }

        return ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '') . $info['filename'] . '.' . $extension;
    }

    /** SQL that swaps the stored download name's extension for the new one. */
    private function renamedOriginalName(string $column, string $extension): string
    {
        return "CASE WHEN {$column} LIKE '%.%' THEN CONCAT(SUBSTRING({$column}, 1, CHAR_LENGTH({$column}) - CHAR_LENGTH(SUBSTRING_INDEX({$column}, '.', -1)) - 1), '.{$extension}') ELSE {$column} END";
    }

    private function kb(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
    }
}
