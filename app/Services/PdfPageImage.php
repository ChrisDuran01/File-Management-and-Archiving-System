<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * The first page of a PDF as a JPEG, drawn by Poppler's pdftoppm (the same
 * tool OCR uses). Used for letterheads: Word's "Save as PDF" of the SG
 * letterhead becomes a sharp page image instead of a blurry screenshot.
 */
class PdfPageImage
{
    /** @return string|null path to a temporary JPEG the caller must delete, or null when the PDF can't be drawn */
    public function firstPage(string $pdfPath, int $dpi = 300): ?string
    {
        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $prefix = $dir.'/letterhead_'.uniqid();

        $process = new Process([
            config('services.ocr.pdftoppm_path'),
            '-jpeg', '-jpegopt', 'quality=92',
            '-r', (string) $dpi,
            '-f', '1', '-l', '1',
            '-singlefile',
            $pdfPath,
            $prefix,
        ]);
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        $jpeg = $prefix.'.jpg';

        return $process->isSuccessful() && is_file($jpeg) && filesize($jpeg) > 0 ? $jpeg : null;
    }
}
