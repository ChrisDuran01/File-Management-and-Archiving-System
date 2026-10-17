<?php

namespace App\Services;

use App\Models\File;
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
    ];

    public static function make(File $file, bool $download = false, array $headers = []): Response
    {
        $headers = array_merge([
            'Content-Type'           => self::MIME_TYPES[strtolower(pathinfo($file->filename, PATHINFO_EXTENSION))] ?? 'application/octet-stream',
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
