<?php

namespace App\Services;

use App\Models\File;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Process\Process;
use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Pulls searchable text out of an uploaded file so it can be indexed for
 * search. PDFs try their embedded text layer first (fast, via pdftotext) and
 * only fall back to page-by-page OCR when that layer is empty, i.e. the PDF
 * is a scanned image rather than a real text document.
 */
class OcrExtractor
{
    /**
     * Below this many characters, a PDF's embedded text layer is treated as
     * absent (a scanned document) rather than a very short real document.
     */
    private const MIN_TEXT_LENGTH = 20;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp'];

    /**
     * Tesseract's own per-word confidence (0-100) below which a "word" is
     * treated as noise (e.g. a signature or seal graphic it tried to read)
     * rather than real text - so those regions are left untouched instead
     * of being blanked out.
     */
    private const MIN_WORD_CONFIDENCE = 40;

    /**
     * Pixel-to-point conversion used when laying the reconstructed page out
     * in the PDF, equivalent to treating the source photo as a 96 DPI scan
     * (96 px/in ÷ 72 pt/in). The absolute DPI assumption doesn't matter for
     * alignment accuracy since every position/size on the page is converted
     * with this same factor - it only sets the PDF's physical page size.
     */
    private const PX_TO_PT = 0.75;

    public function supports(string $extension): bool
    {
        $extension = strtolower($extension);

        return $extension === 'pdf' || in_array($extension, self::IMAGE_EXTENSIONS, true);
    }

    public function isImage(string $extension): bool
    {
        return in_array(strtolower($extension), self::IMAGE_EXTENSIONS, true);
    }

    /**
     * @return string|null Extracted text, or null if this file type isn't handled.
     */
    public function extract(File $file): ?string
    {
        $extension = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));

        if (! $this->supports($extension)) {
            return null;
        }

        [$localPath, $isTemp] = $this->resolveLocalPath($file);

        try {
            return $extension === 'pdf'
                ? $this->extractFromPdf($localPath)
                : $this->ocrImage($localPath);
        } finally {
            if ($isTemp) {
                @unlink($localPath);
            }
        }
    }

    /**
     * Converts a photo of a hardcopy document into a searchable PDF: same
     * page image (after cleanup), with an invisible OCR text layer on top so
     * it's searchable/selectable like a real scanned document.
     *
     * @return string Absolute path to the generated PDF (caller must delete it).
     */
    public function convertToSearchablePdf(string $imagePath): string
    {
        $cleaned = $this->preprocessForOcr($imagePath);
        $outputBase = $this->tempDir() . '/ocr_pdf_' . uniqid();

        try {
            (new TesseractOCR($cleaned))
                ->executable(config('services.ocr.tesseract_path'))
                ->pdf()
                ->setOutputFile($outputBase . '.pdf')
                ->run();
        } finally {
            if ($cleaned !== $imagePath) {
                @unlink($cleaned);
            }
        }

        return $outputBase . '.pdf';
    }

    /**
     * Converts a photo of a hardcopy document into a PDF that keeps the
     * original graphics (letterhead, signature, seal, layout) exactly as
     * photographed, but replaces each recognized line of body text with
     * real, crisp PDF text in the same spot - so the page still looks like
     * the original, but the text itself is never a blurry raster.
     *
     * Falls back to a plain searchable-image PDF (see convertToSearchablePdf)
     * if nothing on the page is confidently recognized as text, rather than
     * producing a page with its content silently erased.
     *
     * @return string Absolute path to the generated PDF (caller must delete it).
     */
    public function buildLayoutPreservedPdf(string $imagePath): string
    {
        $lines = $this->detectTextLines($imagePath);

        if (! $lines) {
            return $this->convertToSearchablePdf($imagePath);
        }

        return $this->renderReconstructedPdf($imagePath, $lines);
    }

    /**
     * @return list<array{left: int, top: int, width: int, height: int, text: string}>
     */
    private function detectTextLines(string $imagePath): array
    {
        $ocrCopy = $this->preprocessForOcr($imagePath);

        try {
            $tsv = (new TesseractOCR($ocrCopy))
                ->executable(config('services.ocr.tesseract_path'))
                ->tsv()
                ->run();
        } finally {
            if ($ocrCopy !== $imagePath) {
                @unlink($ocrCopy);
            }
        }

        $lines = [];

        foreach (explode("\n", trim($tsv)) as $i => $row) {
            if ($i === 0 || $row === '') {
                continue; // header row
            }

            $cols = explode("\t", $row);

            if (count($cols) < 12) {
                continue;
            }

            [$level, , $blockNum, $parNum, $lineNum, , $left, $top, $width, $height, $conf, $text] = $cols;

            if ((int) $level !== 5 || (float) $conf < self::MIN_WORD_CONFIDENCE || trim($text) === '') {
                continue;
            }

            $key = $blockNum . '-' . $parNum . '-' . $lineNum;
            $left = (int) $left;
            $top = (int) $top;
            $right = $left + (int) $width;
            $bottom = $top + (int) $height;

            if (! isset($lines[$key])) {
                $lines[$key] = ['left' => $left, 'top' => $top, 'right' => $right, 'bottom' => $bottom, 'words' => []];
            }

            $lines[$key]['left'] = min($lines[$key]['left'], $left);
            $lines[$key]['top'] = min($lines[$key]['top'], $top);
            $lines[$key]['right'] = max($lines[$key]['right'], $right);
            $lines[$key]['bottom'] = max($lines[$key]['bottom'], $bottom);
            $lines[$key]['words'][] = $text;
        }

        return array_values(array_map(fn ($line) => [
            'left' => $line['left'],
            'top' => $line['top'],
            'width' => $line['right'] - $line['left'],
            'height' => $line['bottom'] - $line['top'],
            'text' => implode(' ', $line['words']),
        ], $lines));
    }

    /**
     * @param list<array{left: int, top: int, width: int, height: int, text: string}> $lines
     */
    private function renderReconstructedPdf(string $imagePath, array $lines): string
    {
        $source = @imagecreatefromstring(file_get_contents($imagePath));

        if (! $source) {
            // Can't decode this image at all for reconstruction - fall back
            // rather than failing the whole conversion.
            return $this->convertToSearchablePdf($imagePath);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $padding = 3;
        $inkColors = [];

        foreach ($lines as $key => $line) {
            $x1 = max(0, $line['left'] - $padding);
            $y1 = max(0, $line['top'] - $padding);
            $x2 = min($width - 1, $line['left'] + $line['width'] + $padding);
            $y2 = min($height - 1, $line['top'] + $line['height'] + $padding);

            // Sample the background just above the line (usually blank
            // paper, not ink) so the erased patch blends in rather than
            // leaving a stark white box on off-white/cream paper.
            $sampleY = max(0, $y1 - 2);
            $backgroundColor = imagecolorat($source, $x1, $sampleY);

            // The line's own ink might be light-on-dark (e.g. white text on
            // a colored letterhead band) rather than dark-on-light, so the
            // replacement text needs to match - detected before the region
            // is erased.
            $inkColors[$key] = $this->estimateInkColor($source, $x1, $y1, $x2, $y2, $backgroundColor);

            imagefilledrectangle($source, $x1, $y1, $x2, $y2, $backgroundColor);
        }

        $cleanedPath = $this->tempDir() . '/ocr_bg_' . uniqid() . '.png';
        imagepng($source, $cleanedPath);

        $imageUri = 'data:image/png;base64,' . base64_encode(file_get_contents($cleanedPath));
        @unlink($cleanedPath);

        $pageWidthPt = $width * self::PX_TO_PT;
        $pageHeightPt = $height * self::PX_TO_PT;

        $viewLines = [];

        foreach ($lines as $key => $line) {
            $viewLines[] = [
                'left' => $line['left'] * self::PX_TO_PT,
                'top' => $line['top'] * self::PX_TO_PT,
                'fontSize' => max(6, $line['height'] * self::PX_TO_PT * 0.85),
                'text' => $line['text'],
                'color' => $inkColors[$key] ?? '#000000',
            ];
        }

        $pdf = DomPdf::loadView('Admin.reconstructed-scan-pdf', [
            'imageUri' => $imageUri,
            'pageWidthPt' => $pageWidthPt,
            'pageHeightPt' => $pageHeightPt,
            'lines' => $viewLines,
        ])->setPaper([0, 0, $pageWidthPt, $pageHeightPt]);

        $outputPath = $this->tempDir() . '/ocr_pdf_' . uniqid() . '.pdf';
        file_put_contents($outputPath, $pdf->output());

        return $outputPath;
    }

    /**
     * Guesses the original text color within a line's box - e.g. white text
     * on a colored letterhead band, not just the usual black ink on white
     * paper - before that region gets erased.
     *
     * A blurry photo of thin text has very few genuinely "pure ink" pixels -
     * most of a glyph's area is a soft blend toward the background - so
     * averaging every above-threshold pixel skews the result toward gray.
     * Instead this keeps only the most extreme-contrast pixels found (the
     * closest thing to true ink the photo has) and averages just those.
     */
    private function estimateInkColor($image, int $x1, int $y1, int $x2, int $y2, int $backgroundColor): string
    {
        $bg = imagecolorsforindex($image, $backgroundColor);
        $bgLuminance = ($bg['red'] * 0.299) + ($bg['green'] * 0.587) + ($bg['blue'] * 0.114);

        $samples = [];
        $stepX = max(1, (int) (($x2 - $x1) / 120));

        for ($y = $y1; $y <= $y2; $y++) {
            for ($x = $x1; $x <= $x2; $x += $stepX) {
                $pixel = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $luminance = ($pixel['red'] * 0.299) + ($pixel['green'] * 0.587) + ($pixel['blue'] * 0.114);
                $contrast = abs($luminance - $bgLuminance);

                if ($contrast > 40) {
                    $samples[] = ['contrast' => $contrast, 'r' => $pixel['red'], 'g' => $pixel['green'], 'b' => $pixel['blue']];
                }
            }
        }

        if (! $samples) {
            return '#000000';
        }

        usort($samples, fn ($a, $b) => $b['contrast'] <=> $a['contrast']);

        $topSamples = array_slice($samples, 0, max(5, (int) (count($samples) * 0.15)));

        $red = array_sum(array_column($topSamples, 'r')) / count($topSamples);
        $green = array_sum(array_column($topSamples, 'g')) / count($topSamples);
        $blue = array_sum(array_column($topSamples, 'b')) / count($topSamples);

        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

    private function extractFromPdf(string $path): string
    {
        $text = trim(Pdf::getText($path, config('services.ocr.pdftotext_path')));

        if (mb_strlen($text) >= self::MIN_TEXT_LENGTH) {
            return $text;
        }

        // No usable text layer - this is a scanned PDF, not a text one.
        return $this->ocrScannedPdf($path);
    }

    private function ocrScannedPdf(string $pdfPath): string
    {
        $prefix = $this->tempDir() . '/ocr_page_' . uniqid();

        $process = new Process([
            config('services.ocr.pdftoppm_path'),
            '-png',
            '-r', '200',
            $pdfPath,
            $prefix,
        ]);
        $process->setTimeout(180);
        $process->run();

        $pageImages = glob($prefix . '*.png') ?: [];
        sort($pageImages);

        $pages = [];

        foreach ($pageImages as $pageImage) {
            $pages[] = $this->ocrImage($pageImage);
            @unlink($pageImage);
        }

        return trim(implode("\n\n", array_filter($pages)));
    }

    /**
     * OCR a single already-rasterized page image (e.g. a page PNG the scan
     * pipeline has already produced with pdftoppm), applying the same
     * GD-only cleanup the PDF path uses. Public wrapper over ocrImage() so
     * callers outside this class don't re-implement the preprocessing.
     */
    public function ocrImageFile(string $imagePath): string
    {
        return $this->ocrImage($imagePath);
    }

    private function ocrImage(string $imagePath): string
    {
        $cleaned = $this->preprocessForOcr($imagePath);

        try {
            return trim((new TesseractOCR($cleaned))
                ->executable(config('services.ocr.tesseract_path'))
                ->run());
        } finally {
            if ($cleaned !== $imagePath) {
                @unlink($cleaned);
            }
        }
    }

    /**
     * Cleans up a photo before OCR: grayscale, contrast boost, and a light
     * sharpen. This is the single biggest accuracy lever available without
     * ImageMagick/OpenCV (not installed here) - it won't fix a badly skewed
     * or blurry photo, but it meaningfully helps the common case of a phone
     * photo with uneven lighting or low contrast.
     *
     * @return string Path to the cleaned-up copy, or the original path
     *                 unchanged if GD can't decode the file.
     */
    private function preprocessForOcr(string $imagePath): string
    {
        $source = @imagecreatefromstring(file_get_contents($imagePath));

        if (! $source) {
            return $imagePath;
        }

        imagefilter($source, IMG_FILTER_GRAYSCALE);
        imagefilter($source, IMG_FILTER_CONTRAST, -15);

        $sharpen = [
            [0, -1, 0],
            [-1, 5, -1],
            [0, -1, 0],
        ];
        imageconvolution($source, $sharpen, 1, 0);

        $outputPath = $this->tempDir() . '/ocr_clean_' . uniqid() . '.png';
        imagepng($source, $outputPath);

        return $outputPath;
    }

    private function tempDir(): string
    {
        $tempDir = storage_path('app/temp');

        if (! FileFacade::exists($tempDir)) {
            FileFacade::makeDirectory($tempDir, 0777, true);
        }

        return $tempDir;
    }

    /**
     * @return array{0: string, 1: bool} [absolute path, whether the caller must delete it]
     */
    private function resolveLocalPath(File $file): array
    {
        if ($file->local_path && Storage::disk('public')->exists($file->local_path)) {
            return [Storage::disk('public')->path($file->local_path), false];
        }

        // Cloud-only file (no local copy) - fetch it from the cloud disk
        // into a temp file, retrying since Supabase reads have shown the
        // same intermittent timeouts/connection resets as writes (see
        // storage/logs/laravel.log) - a single attempt failed real uploads
        // often enough to be the actual cause of "OCR/scan never ran".
        $attempt = 0;
        $lastError = null;

        while ($attempt < 3) {
            $attempt++;

            try {
                $contents = Storage::disk('cloud')->get($file->filepath);

                $extension = pathinfo($file->filename, PATHINFO_EXTENSION);
                $tempPath  = $this->tempDir() . '/ocr_src_' . uniqid() . '.' . $extension;

                FileFacade::put($tempPath, $contents);

                return [$tempPath, true];
            } catch (\Throwable $e) {
                $lastError = $e;

                if ($attempt < 3) {
                    usleep(2_000_000);
                }
            }
        }

        throw new \RuntimeException("Could not fetch file '{$file->filename}' from storage for OCR.", 0, $lastError);
    }
}
