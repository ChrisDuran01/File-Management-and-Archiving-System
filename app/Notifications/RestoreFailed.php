<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A system restore crashed. Sent to SuperAdmins only - in-app + email,
 * mirroring BackupFailed. A restore that dies partway through can leave the
 * database and cloud storage out of sync with each other, so SuperAdmins
 * need to know immediately rather than discovering it from user reports.
 */
class RestoreFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $error,
        private readonly string $backupName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'   => 'Restore failed',
            'message' => Str::limit("Restoring from {$this->backupName}: {$this->error}", 140),
            'link'    => route('backup.index'),
            'icon'    => 'bx bx-error',
            'color'   => '#dc2626',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('QSU-FMAS restore FAILED')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("A restore from backup \"{$this->backupName}\" did not complete.")
            ->line('Error: ' . Str::limit($this->error, 300))
            ->action('Open backup settings', route('backup.index'))
            ->line('The system may be left in a partially restored state - verify data before relying on it.');
    }
}
