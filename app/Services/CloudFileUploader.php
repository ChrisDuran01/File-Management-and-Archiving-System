<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads a file to the cloud disk, retrying a few times before giving up.
 * Supabase has shown repeated intermittent timeouts/connection resets (see
 * storage/logs/laravel.log) that a single-attempt upload had no resilience
 * against at all. Shared by the upload flow (FileController) and the
 * scheduled command that retries local-only files later, so both retry the
 * same way.
 */
class CloudFileUploader
{
    private const RETRIES = 3;
    private const RETRY_DELAY_MS = 2000;

    public function upload(string $cloudPath, string $localFullPath): bool
    {
        $attempt = 0;

        while ($attempt < self::RETRIES) {
            $attempt++;
            $stream = fopen($localFullPath, 'r');

            try {
                // The cloud disk reports a failed write by returning false
                // ('throw' => false), not by throwing - so the answer is
                // checked rather than assumed. Stored under the same short
                // list of types FileResponse serves: a link straight to the
                // cloud copy is served with whatever type it was stored as.
                $stored = $stream && Storage::disk('cloud')->writeStream($cloudPath, $stream, [
                    'ContentType' => FileResponse::contentTypeFor($cloudPath),
                    'mimetype'    => FileResponse::contentTypeFor($cloudPath),
                ]);

                if ($stored) {
                    return true;
                }

                Log::warning("Cloud upload attempt {$attempt} failed for {$cloudPath}: the storage refused it.");
            } catch (\Throwable $e) {
                Log::warning("Cloud upload attempt {$attempt} failed for {$cloudPath}: " . $e->getMessage());
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if ($attempt < self::RETRIES) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }
        }

        Log::error("All {$attempt} cloud upload attempts failed for {$cloudPath}");

        return false;
    }
}
