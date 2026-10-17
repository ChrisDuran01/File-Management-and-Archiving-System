<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Support\Str;

/**
 * Tells every student who tapped "Notify me" that an officer made a
 * document public.
 */
class SendDocumentPush extends SendStudentPush
{
    public function __construct(private readonly int $fileId)
    {
    }

    protected function payload(): ?array
    {
        // Checked when the push goes out, not when it was queued: by then the
        // file may be private again, in Trash, or inside a restricted folder
        // (which hides it from students even while it is marked public).
        $file = File::publiclyVisible()->find($this->fileId);

        if (! $file) {
            return null;
        }

        return [
            'title' => 'New SG document',
            'body'  => Str::limit($file->filename, 120),
            // ?via=push lets Reports count the visit; doc= makes the page open this document.
            'url'   => route('student.dashboard') . '?via=push&doc=' . $file->id . '#documents',
            'tag'   => 'document-' . $file->id,
        ];
    }

    protected function label(): string
    {
        return 'Document push #' . $this->fileId;
    }
}
