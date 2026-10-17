<?php

namespace App\Jobs;

use App\Models\Announcement;
use Illuminate\Support\Str;

/**
 * Tells every student who tapped "Notify me" that an officer posted a new
 * announcement.
 */
class SendAnnouncementPush extends SendStudentPush
{
    public function __construct(private readonly Announcement $announcement)
    {
    }

    protected function payload(): ?array
    {
        // Plain text only - the announcement body is HTML and has no place in a notification.
        return [
            'title' => 'New SG announcement',
            'body'  => Str::limit($this->announcement->title, 120),
            // ?via=push lets Reports count visits that came from tapping a notification.
            'url'   => route('student.dashboard') . '?via=push#announcements',
            'tag'   => 'announcement-' . $this->announcement->id,
        ];
    }

    protected function label(): string
    {
        return 'Announcement push #' . $this->announcement->id;
    }
}
