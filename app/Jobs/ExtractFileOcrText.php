<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\CloudFileUploader;
use App\Services\OcrExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs OCR/text extraction for one uploaded file, off the request cycle -
 * extraction can take several seconds per page, which would otherwise stall
 * the upload response.
 */
class ExtractFileOcrText implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        private readonly int $fileId,
        private readonly bool $convertToPdf = false,
    ) {
    }

    public function handle(OcrExtractor $extractor, CloudFileUploader $uploader): void
    {
        $file = File::find($this->fileId);

        if (! $file) {
            return;
        }

        $extension = pathinfo($file->filename, PATHINFO_EXTENSION);

        if (! $extractor->supports($extension)) {
            $file->forceFill(['ocr_status' => 'skipped'])->save();

            return;
        }

        try {
            $text = $extractor->extract($file);

            if ($this->convertToPdf && $extractor->isImage($extension)) {
                $this->replaceWithSearchablePdf($file, $extractor, $uploader);
            }

            $file->forceFill([
                'ocr_text'         => $text,
                'ocr_status'       => 'done',
                'ocr_processed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::error('OCR extraction failed for file ' . $file->filename . ': ' . $e->getMessage());

            $file->forceFill([
                'ocr_status'       => 'failed',
                'ocr_processed_at' => now(),
            ])->save();
        }
    }

    /**
     * Swaps a photo of a hardcopy document for a searchable PDF: the photo
     * itself is embedded completely untouched (nothing erased or redrawn),
     * with an invisible OCR text layer on top so it's searchable/
     * selectable - then removes the original photo (local + Supabase)
     * since the PDF replaces it as the file's actual content.
     *
     * Deliberately not using OcrExtractor::buildLayoutPreservedPdf() (the
     * erase-and-redraw-text-in-place reconstruction) here - it produced
     * visibly glitchy results often enough (flat-color patches that don't
     * blend into real paper texture/lighting, font mismatches, misaligned
     * boxes on skewed photos) that a page which always looks exactly like
     * the original photo was judged the safer default. That method is kept
     * in OcrExtractor for future revisiting, just not called from here.
     */
    private function replaceWithSearchablePdf(File $file, OcrExtractor $extractor, CloudFileUploader $uploader): void
    {
        $hasLocalCopy = $file->local_path && Storage::disk('public')->exists($file->local_path);
        $tempSourcePath = null;

        if ($hasLocalCopy) {
            $sourcePath = Storage::disk('public')->path($file->local_path);
        } else {
            // Direct-to-cloud uploads have no local copy yet at this point
            // (CacheFileLocally is a separate, independently-timed job) -
            // fetch it from the cloud into a temp file instead, with a
            // short retry since Supabase reads have shown the same
            // intermittent timeouts as writes (see storage/logs/laravel.log).
            $tempSourcePath = $this->fetchFromCloudWithRetry($file);
            $sourcePath = $tempSourcePath;
        }

        try {
            $pdfPath = $extractor->convertToSearchablePdf($sourcePath);
        } finally {
            if ($tempSourcePath) {
                @unlink($tempSourcePath);
            }
        }

        try {
            $newFilename = Str::beforeLast($file->filename, '.') . '.pdf';
            $pdfContents = file_get_contents($pdfPath);

            // Always (re)establish a local copy for the resulting PDF, even
            // if the original image never had one - it's already been
            // fetched from the cloud above, so this is effectively free.
            $newLocalPath = 'uploads/' . ($file->folder_id ?? 'root') . '/' . Str::random(40) . '.pdf';
            Storage::disk('public')->put($newLocalPath, $pdfContents);

            if ($hasLocalCopy) {
                Storage::disk('public')->delete($file->local_path);
            }

            $oldFilepath = $file->filepath;
            $newFilepath = $oldFilepath ? Str::beforeLast($oldFilepath, '.') . '.pdf' : null;

            if ($newFilepath) {
                // Retries on the same Supabase flakiness everything else in
                // this app has had to account for - a single-attempt write
                // here previously threw away an already-completed OCR/PDF
                // conversion on one transient failure.
                if (! $uploader->upload($newFilepath, $pdfPath)) {
                    throw new \RuntimeException("Could not upload converted PDF to cloud storage for file {$file->id}.");
                }

                Storage::disk('cloud')->delete($oldFilepath);
            }

            $file->forceFill([
                'filename'     => $newFilename,
                'local_path'   => $newLocalPath,
                'filepath'     => $newFilepath ?? $file->filepath,
                'size'         => strlen($pdfContents),
                'storage_type' => 'both',
            ])->save();
        } finally {
            @unlink($pdfPath);
        }
    }

    /**
     * @return string Absolute path to a temp file holding the cloud copy - caller must delete it.
     */
    private function fetchFromCloudWithRetry(File $file): string
    {
        $tempDir = storage_path('app/temp');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $extension = pathinfo($file->filename, PATHINFO_EXTENSION);
        $tempPath  = $tempDir . '/ocr_pdf_src_' . uniqid() . '.' . $extension;

        $attempt = 0;
        $lastError = null;

        while ($attempt < 3) {
            $attempt++;

            try {
                file_put_contents($tempPath, Storage::disk('cloud')->get($file->filepath));

                return $tempPath;
            } catch (Throwable $e) {
                $lastError = $e;
                Log::warning("Cloud fetch attempt {$attempt} failed for {$file->filepath} (PDF conversion): " . $e->getMessage());

                if ($attempt < 3) {
                    usleep(2_000_000);
                }
            }
        }

        throw new \RuntimeException("Could not fetch file '{$file->filename}' from storage for PDF conversion.", 0, $lastError);
    }
}
