<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Scan batch processing ended at `failed` - the hardcopy someone fed the
 * scanner did NOT become a record and a human has to intervene (fix the
 * file and Reprocess, or Discard). In-app + email.
 */
class ScanBatchFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $batchId,
        private readonly string $originalFilename,
        private readonly string $error,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'   => 'Scan processing failed',
            'message' => $this->originalFilename . ' could not be processed: ' . \Illuminate\Support\Str::limit($this->error, 120),
            'link'    => route('documents.scans.index'),
            'icon'    => 'bx bx-error',
            'color'   => '#dc2626',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Scan processing failed — ' . $this->originalFilename)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A scanned batch failed to process, so it has NOT become a document record.')
            ->line('File: ' . $this->originalFilename)
            ->line('Reason: ' . \Illuminate\Support\Str::limit($this->error, 300))
            ->action('Open scan inbox', route('documents.scans.index'))
            ->line('You can re-process it after fixing the file, or discard it.');
    }
}
