<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A stored file's bytes as a response from this server - its local copy when
 * there is one, otherwise the cloud copy. Same-origin on purpose: a signed
 * cloud URL keeps working for an hour whatever happens here, so it could
 * outlive a share link being turned off, and the phone app could never find
 * a copy it saved under it again. Callers check access first.
 */
class FileResponse
{
    /**
     * Only these types are sent as what they are; anything else goes out as
     * application/octet-stream, so a browser never runs an uploaded file as
     * a page.
     */
    private const MIME_TYPES = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'txt'  => 'text/plain', 'md' => 'text/plain', 'csv' => 'text/plain', 'log' => 'text/plain',
        'zip'  => 'application/zip',
    ];

    /**
     * The type a file is stored and served with, from its name - the same
     * short list as above, octet-stream for anything else. Also used when a
     * file is put in cloud storage, because a signed cloud link is served
     * with whatever type the file was stored under.
     */
    public static function contentTypeFor(string $name): string
    {
        return self::MIME_TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    /**
     * Whether the cloud disk is (still) Supabase, which needs its own calls
     * in a few places - asked of the disk actually in use, not of config.
     * Goes away with the Supabase package once the move to Backblaze is done.
     */
    public static function cloudIsSupabase(): bool
    {
        return Storage::disk('cloud')->getAdapter() instanceof \QuixLabs\LaravelSupabaseFlysystem\SupabaseAdapter;
    }

    /**
     * A link straight to a cloud file that works for $minutes. Supabase has
     * to be asked for one over the internet (0.4-3 s); a Backblaze (S3) link
     * is signed right here, with no network call.
     */
    public static function cloudLink(string $path, int $minutes = 60): string
    {
        if (self::cloudIsSupabase()) {
            return Storage::disk('cloud')->getAdapter()->getSignedUrl($path, ['expiresIn' => $minutes * 60]);
        }

        return Storage::disk('cloud')->temporaryUrl($path, now()->addMinutes($minutes));
    }

    /**
     * Where a preview page points its img / iframe / video: a cloud link,
     * or - for a file whose cloud copy isn't there yet, or when the cloud
     * can't be reached - this server's own route to its local copy, which
     * checks access first (FileController::raw()). Null when neither exists.
     */
    public static function previewUrl(File $file): ?string
    {
        if ($file->storage_type !== 'local') {
            try {
                return self::cloudLink($file->filepath);
            } catch (\Throwable $e) {
                Log::warning("Cloud link failed for file {$file->id}, falling back to local: " . $e->getMessage());
            }
        }

        return $file->local_path && Storage::disk('local')->exists($file->local_path)
            ? route('files.raw', $file->id)
            : null;
    }

    public static function make(File $file, bool $download = false, array $headers = []): Response
    {
        $headers = array_merge([
            'Content-Type'           => self::contentTypeFor($file->filename),
            'Content-Disposition'    => ($download ? 'attachment' : 'inline') . '; filename="' . addslashes(Str::ascii($file->filename)) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ], $headers);

        if ($file->local_path && Storage::disk('local')->exists($file->local_path)) {
            return response()->file(Storage::disk('local')->path($file->local_path), $headers);
        }

        try {
            $contents = Storage::disk('cloud')->get($file->filepath);
        } catch (\Throwable) {
            $contents = null;
        }

        abort_if($contents === null, 404, 'This file could not be loaded.');

        return response($contents, 200, $headers);
    }
}
