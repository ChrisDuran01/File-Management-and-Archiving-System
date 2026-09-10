<?php

namespace App\Notifications;

use App\Models\ScanBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A scan batch finished OCR/split processing and is waiting for an
 * operator on the review screen. Sent to every active officer - in-app
 * always, plus email since a waiting batch is exactly the thing someone
 * should hear about while not in the app.
 */
class ScanBatchReadyForReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $batchId,
        private readonly string $originalFilename,
        private readonly int $segmentCount,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'   => 'Scan ready for review',
            'message' => $this->originalFilename . ' was processed into ' . $this->segmentCount . ' proposed document(s).',
            'link'    => route('documents.scans.review', $this->batchId),
            'icon'    => 'bx bx-import',
            'color'   => '#534AB7',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Scan ready for review — ' . $this->originalFilename)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A scanned batch has finished processing and is waiting to be reviewed and filed.')
            ->line('File: ' . $this->originalFilename)
            ->line('Proposed documents: ' . $this->segmentCount)
            ->action('Open review screen', route('documents.scans.review', $this->batchId))
            ->line('Anyone on the team can pick this up — it only needs to be filed once.');
    }
}
