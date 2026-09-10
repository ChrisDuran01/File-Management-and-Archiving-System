<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\ScanBatch;
use App\Models\User;
use App\Notifications\ScanBatchFailed;
use App\Notifications\ScanBatchReadyForReview;
use App\Services\DocumentFieldParser;
use Illuminate\Support\Facades\Notification;
use App\Services\OcrExtractor;
use App\Services\PdfToolkit;
use App\Services\ScanSeparatorDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * OCRs every page of a freshly ingested scan batch, proposes where it
 * should be split into separate documents, and pre-fills the metadata
 * guess for each segment - then parks the batch at `ready_for_review` for
 * an operator. Nothing is filed here.
 *
 * Retry model mirrors CacheFileLocally: a handful of backing-off retries
 * for transient/infra failures (Process timeouts, disk), but a "domain"
 * failure the retry can't fix (corrupt PDF, no pages, over the page limit)
 * ends the batch at `failed` with a message and no retry.
 */
class ProcessScanBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** 1m, 5m, 15m - long enough to ride out a brief Poppler/OCR hiccup. */
    public array $backoff = [60, 300, 900];

    public int $timeout = 600;

    public function __construct(private readonly int $batchId)
    {
    }

    public function handle(
        OcrExtractor $ocr,
        PdfToolkit $pdf,
        ScanSeparatorDetector $detector,
        DocumentFieldParser $parser
    ): void {
        $batch = ScanBatch::find($this->batchId);

        // Gone, already processed, or currently being filed - a re-dispatch
        // or retry after a successful run must be a no-op.
        if (! $batch || $batch->status !== 'extracting') {
            return;
        }

        // Respects the batch's own stored_disk rather than assuming - older
        // rows ingested before this pipeline moved off the public disk still
        // resolve correctly.
        $rawPath = Storage::disk($batch->stored_disk)->path($batch->stored_path);
        $isImage = $ocr->isImage((string) $batch->extension);

        $generatedPdf = null;
        $images = [];

        try {
            $pdfPath = $rawPath;

            if ($isImage) {
                $pdfPath = $generatedPdf = $pdf->imageToPdf($rawPath);
            }

            $pageCount = $pdf->pageCount($pdfPath);

            if ($pageCount < 1) {
                $this->failDomain($batch, 'The file has no readable pages - it may be corrupt or empty.');
                return;
            }

            if ($pageCount > (int) config('scan.max_pages')) {
                $this->failDomain(
                    $batch,
                    "The batch has {$pageCount} pages, over the limit of " . config('scan.max_pages')
                    . '. Split it before scanning, or raise SCAN_MAX_PAGES and re-process.'
                );
                return;
            }

            $images = $pdf->rasterizeAllPages($pdfPath, (int) config('scan.ocr_dpi'));

            $pages = [];
            $pageTexts = [];

            for ($n = 1; $n <= $pageCount; $n++) {
                $image = $images[$n] ?? null;
                $text = '';

                try {
                    $text = $pdf->pageText($pdfPath, $n);

                    if (mb_strlen(trim($text)) < 20 && $image) {
                        $text = $ocr->ocrImageFile($image);
                    }
                } catch (Throwable $e) {
                    // One unreadable page never fails the whole batch.
                    Log::warning("ProcessScanBatch #{$batch->id}: page {$n} text extraction failed: " . $e->getMessage());
                    $text = '';
                }

                $text = trim($text);
                $pageTexts[$n] = $text;
                $pages[$n] = ['number' => $n, 'image' => $image ?? '', 'text' => $text];
            }

            $result = $detector->detect($pages);

            $segments = [];
            foreach ($result['segments'] as $seg) {
                $firstText  = $pageTexts[$seg['start_page']] ?? '';
                $secondText = $pageTexts[$seg['start_page'] + 1] ?? '';

                $segments[] = [
                    'start_page' => $seg['start_page'],
                    'end_page'   => $seg['end_page'],
                    'guessed'    => $parser->parse(trim($firstText . "\n" . $secondText)),
                ];
            }

            $batch->update([
                'status'              => 'ready_for_review',
                'page_count'          => $pageCount,
                'segments'            => $segments,
                'segment_count'       => count($segments),
                'dropped_pages'       => $result['dropped_pages'],
                'detected_separators' => $result['method'],
                'ocr_text'            => trim(implode("\n\n", $pageTexts)),
                'processed_at'        => now(),
            ]);

            Notification::send(
                User::activeOfficers(),
                new ScanBatchReadyForReview($batch->id, $batch->original_filename, count($segments))
            );
        } finally {
            foreach ($images as $image) {
                @unlink($image);
            }
            if ($generatedPdf) {
                @unlink($generatedPdf);
            }
        }
    }

    /**
     * A failure a retry can't fix - stop here with a message for the
     * operator instead of burning the retry budget.
     */
    private function failDomain(ScanBatch $batch, string $message): void
    {
        $batch->update([
            'status'       => 'failed',
            'error'        => $message,
            'processed_at' => now(),
        ]);

        Log::error("ProcessScanBatch #{$batch->id} failed: {$message}");

        ActivityLog::create([
            'user_name'  => 'System',
            'activity'   => "Scan batch #{$batch->id} processing failed: {$message}",
            'ip_address' => null,
        ]);

        Notification::send(
            User::activeOfficers(),
            new ScanBatchFailed($batch->id, $batch->original_filename, $message)
        );
    }

    public function failed(Throwable $e): void
    {
        $batch = ScanBatch::find($this->batchId);

        if (! $batch || in_array($batch->status, ['filed', 'ready_for_review'], true)) {
            return;
        }

        $batch->update([
            'status'       => 'failed',
            'error'        => substr($e->getMessage(), 0, 2000),
            'processed_at' => now(),
        ]);

        Log::error("ProcessScanBatch #{$this->batchId} permanently failed: " . $e->getMessage());

        ActivityLog::create([
            'user_name'  => 'System',
            'activity'   => "Scan batch #{$this->batchId} processing failed",
            'ip_address' => null,
        ]);

        Notification::send(
            User::activeOfficers(),
            new ScanBatchFailed($batch->id, $batch->original_filename, substr($e->getMessage(), 0, 300))
        );
    }
}
