<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Restores the "two independent copies" guarantee for files uploaded
 * directly to the cloud (FileController::confirmUpload()) - those never
 * touch this server's disk at upload time, so this fetches the file back
 * down from the cloud, once, and caches it locally. Dispatched right after
 * the cloud upload is confirmed, but runs purely in the background - the
 * uploader is never made to wait for this.
 */
class CacheFileLocally implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * 1m, 5m, 15m, 30m, 1h - Supabase has shown outages longer than a
     * simple fixed retry would ride out (see storage/logs/laravel.log),
     * so this backs off further with each attempt instead of hammering it.
     */
    public array $backoff = [60, 300, 900, 1800, 3600];

    public function __construct(private readonly int $fileId)
    {
    }

    public function handle(): void
    {
        $file = File::find($this->fileId);

        // Already cached (or the file/record is gone) - nothing to do.
        if (! $file || $file->local_path) {
            return;
        }

        $contents = Storage::disk('cloud')->get($file->filepath);

        $extension = pathinfo($file->filename, PATHINFO_EXTENSION);
        $localPath = 'uploads/' . ($file->folder_id ?? 'root') . '/' . Str::random(40) . ($extension ? '.' . $extension : '');

        Storage::disk('public')->put($localPath, $contents);

        $file->forceFill([
            'local_path'   => $localPath,
            'storage_type' => 'both',
        ])->save();

        Log::info("Cached local copy for file {$file->id}: {$file->filename}");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("CacheFileLocally permanently failed for file {$this->fileId}: " . $exception->getMessage());
    }
}
