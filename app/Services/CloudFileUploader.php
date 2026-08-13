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
        $contents = file_get_contents($localFullPath);
        $attempt = 0;

        while ($attempt < self::RETRIES) {
            $attempt++;

            try {
                Storage::disk('cloud')->put($cloudPath, $contents);

                return true;
            } catch (\Throwable $e) {
                Log::warning("Cloud upload attempt {$attempt} failed for {$cloudPath}: " . $e->getMessage());

                if ($attempt < self::RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000);
                }
            }
        }

        Log::error("All {$attempt} cloud upload attempts failed for {$cloudPath}");

        return false;
    }
}
