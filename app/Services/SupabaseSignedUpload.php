<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Generates Supabase Storage signed upload URLs so the browser can PUT a
 * file directly to Supabase without routing the bytes through this app's
 * server - one hop instead of two, and upload reliability no longer
 * depends on this server's own connection to Supabase, just the browser's.
 *
 * The installed Flysystem adapter (quix-labs/laravel-supabase-flysystem)
 * only wraps signed *download* URLs, not upload ones, so this calls
 * Supabase's Storage REST API directly - same pattern already used in
 * BackupController for listing bucket contents.
 */
class SupabaseSignedUpload
{
    public function createUrl(string $cloudPath): ?string
    {
        $bucket = env('SUPABASE_BUCKET');
        $signUrl = rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/upload/sign/' . $bucket . '/' . ltrim($cloudPath, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'apikey'        => env('SUPABASE_SERVICE_KEY'),
        ])->post($signUrl);

        if (! $response->successful() || ! $response->json('token')) {
            return null;
        }

        return $signUrl . '?token=' . $response->json('token');
    }
}
