<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * An officer posted a new announcement. In-app only - announcements are
 * frequent enough that emailing each one would read as spam.
 */
class AnnouncementPosted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $authorName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'   => 'New announcement',
            'message' => '"' . Str::limit($this->title, 80) . '" posted by ' . $this->authorName . '.',
            'link'    => route('announcements.index'),
            'icon'    => 'bx bx-bullhorn',
            'color'   => '#058028',
        ];
    }
}
