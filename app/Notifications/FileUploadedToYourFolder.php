<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Someone uploaded file(s) into a folder you created. In-app only, and
 * never sent for uploads into your own folder by yourself.
 */
class FileUploadedToYourFolder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $folderId,
        private readonly string $folderName,
        private readonly string $uploaderName,
        private readonly int $fileCount,
        private readonly string $firstFilename,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $what = $this->fileCount === 1
            ? Str::limit($this->firstFilename, 60)
            : $this->fileCount . ' files';

        return [
            'title'   => 'Upload into your folder',
            'message' => $this->uploaderName . ' uploaded ' . $what . ' into "' . $this->folderName . '".',
            'link'    => route('folders.show', $this->folderId),
            'icon'    => 'bx bx-folder-plus',
            'color'   => '#2563eb',
        ];
    }
}
