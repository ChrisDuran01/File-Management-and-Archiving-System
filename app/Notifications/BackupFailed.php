<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A backup run crashed. Sent to SuperAdmins only - in-app + email, since a
 * silently failing backup is the single most dangerous quiet failure this
 * system can have.
 */
class BackupFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $error)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'   => 'Backup failed',
            'message' => Str::limit($this->error, 140),
            'link'    => route('backup.index'),
            'icon'    => 'bx bx-shield-x',
            'color'   => '#dc2626',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('QSU-FMAS backup FAILED')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A scheduled or manual backup did not complete.')
            ->line('Error: ' . Str::limit($this->error, 300))
            ->action('Open backup settings', route('backup.index'))
            ->line('Until a backup succeeds, recent changes are not protected.');
    }
}
