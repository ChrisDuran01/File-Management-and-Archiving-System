<?php

namespace App\Notifications;

use App\Models\Archive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A scheduled fixity re-check (archives:verify-fixity) found an archive ZIP
 * whose bytes no longer match the checksum recorded when it was created -
 * silent corruption, tampering, or storage failure. Sent to SuperAdmins
 * only, mirroring BackupFailed: this is the archival equivalent of a
 * quietly failing backup, and needs a human to look at it before someone
 * tries to restore from it.
 */
class ArchiveFixityFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Archive $archive)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'   => 'Archive integrity check failed',
            'message' => "\"{$this->archive->folder_name}\" no longer matches its recorded checksum.",
            'link'    => route('archives.index'),
            'icon'    => 'bx bx-error-circle',
            'color'   => '#dc2626',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('QSU-FMAS archive integrity check FAILED')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("The scheduled fixity check found that the archive \"{$this->archive->folder_name}\" (archived " . $this->archive->archived_at?->format('M j, Y') . ") no longer matches the checksum recorded when it was created.")
            ->line('This usually means storage corruption or an altered file, not a false alarm - the check only compares raw bytes against a hash.')
            ->action('Open archives', route('archives.index'))
            ->line('Verify from another backup or copy before relying on this archive.');
    }
}
