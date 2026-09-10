<?php

namespace App\Services;

use Illuminate\Support\Facades\File as FileFacade;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Thin wrapper over the Poppler command-line tools (pdfinfo, pdftoppm,
 * pdftotext, pdfseparate, pdfunite) used by the scan-inbox split pipeline.
 * Every call goes through here so binary paths, timeouts, Windows-safe
 * argument quoting and temp-file handling live in one place.
 *
 * All temp output lands in storage/app/temp (the same scratch dir
 * OcrExtractor uses). Callers own deleting whatever a method hands back.
 */
class PdfToolkit
{
    public function __construct(private readonly OcrExtractor $ocr)
    {
    }

    /**
     * Number of pages in a PDF. Uses pdfinfo; falls back to counting a
     * low-DPI rasterization if pdfinfo can't parse the file. Returns 0
     * (rather than throwing) when neither tool can make sense of the file -
     * the caller treats 0 pages as "corrupt/unreadable" and fails the batch
     * without burning retries.
     */
    public function pageCount(string $pdfPath): int
    {
        try {
            $output = $this->run([
                config('services.ocr.pdfinfo_path'),
                $pdfPath,
            ]);

            if (preg_match('/^Pages:\s+(\d+)/m', $output, $m)) {
                return (int) $m[1];
            }
        } catch (\Throwable $e) {
            // fall through to the rasterize-and-count fallback
        }

        try {
            $prefix = $this->tempPath('pt_count_');
            $this->run([
                config('services.ocr.pdftoppm_path'),
                '-png', '-r', '10',
                $pdfPath, $prefix,
            ]);

            $pages = glob($prefix . '*.png') ?: [];
            foreach ($pages as $p) {
                @unlink($p);
            }

            return count($pages);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Rasterize one page to a PNG. Returns the PNG's absolute path
     * (caller deletes).
     */
    public function rasterizePage(string $pdfPath, int $page, int $dpi): string
    {
        $prefix = $this->tempPath('pt_page_');

        $this->run([
            config('services.ocr.pdftoppm_path'),
            '-png', '-r', (string) $dpi,
            '-f', (string) $page, '-l', (string) $page,
            $pdfPath, $prefix,
        ]);

        $matches = glob($prefix . '*.png') ?: [];

        if (! $matches) {
            throw new RuntimeException("pdftoppm produced no output for page {$page}.");
        }

        return $matches[0];
    }

    /**
     * Rasterize every page. Returns a map of 1-based page number => PNG
     * absolute path, ordered. Caller deletes every value.
     *
     * @return array<int, string>
     */
    public function rasterizeAllPages(string $pdfPath, int $dpi): array
    {
        $prefix = $this->tempPath('pt_all_');

        $this->run([
            config('services.ocr.pdftoppm_path'),
            '-png', '-r', (string) $dpi,
            $pdfPath, $prefix,
        ]);

        $files = glob($prefix . '*.png') ?: [];

        $pages = [];
        foreach ($files as $file) {
            // pdftoppm names output "<prefix>-<n>.png", zero-padded to the
            // width of the highest page number.
            if (preg_match('/-(\d+)\.png$/', $file, $m)) {
                $pages[(int) $m[1]] = $file;
            }
        }

        ksort($pages);

        return $pages;
    }

    /**
     * Embedded text layer for one page (empty string for a scanned image
     * page). Uses pdftotext -layout.
     */
    public function pageText(string $pdfPath, int $page): string
    {
        $output = $this->run([
            config('services.ocr.pdftotext_path'),
            '-layout',
            '-f', (string) $page, '-l', (string) $page,
            $pdfPath, '-',
        ]);

        return trim($output);
    }

    /**
     * Extract an inclusive page range into a fresh single PDF. Returns the
     * new PDF's absolute path (caller deletes).
     */
    public function extractRange(string $pdfPath, int $start, int $end): string
    {
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $outPath = $this->tempPath('pt_seg_') . '.pdf';

        // pdfseparate replaces %d with the ORIGINAL page number, so with
        // -f 3 -l 5 it writes seg-3.pdf, seg-4.pdf, seg-5.pdf.
        $sepPattern = $this->tempPath('pt_sep_') . '-%d.pdf';

        $this->run([
            config('services.ocr.pdfseparate_path'),
            '-f', (string) $start, '-l', (string) $end,
            $pdfPath, $sepPattern,
        ]);

        $globBase = str_replace('-%d.pdf', '-*.pdf', $sepPattern);
        $parts = glob($globBase) ?: [];

        // order by the numeric suffix
        usort($parts, function ($a, $b) {
            preg_match('/-(\d+)\.pdf$/', $a, $ma);
            preg_match('/-(\d+)\.pdf$/', $b, $mb);
            return ((int) ($ma[1] ?? 0)) <=> ((int) ($mb[1] ?? 0));
        });

        if (! $parts) {
            throw new RuntimeException("pdfseparate produced no pages for range {$start}-{$end}.");
        }

        try {
            if (count($parts) === 1) {
                if (! @copy($parts[0], $outPath)) {
                    throw new RuntimeException('Could not copy the single extracted page.');
                }
            } else {
                $this->run(array_merge(
                    [config('services.ocr.pdfunite_path')],
                    $parts,
                    [$outPath]
                ));
            }
        } finally {
            foreach ($parts as $part) {
                @unlink($part);
            }
        }

        return $outPath;
    }

    /**
     * Turn a standalone image into a real searchable PDF (original raster
     * + invisible OCR text layer) so an image-only scan batch can be filed
     * as a PDF like every other document. Returns the PDF's absolute path
     * (caller deletes).
     */
    public function imageToPdf(string $imagePath): string
    {
        return $this->ocr->convertToSearchablePdf($imagePath);
    }

    /**
     * Run a process, returning stdout. Throws with stderr on non-zero exit.
     *
     * @param array<int, string> $command
     */
    private function run(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout((float) config('scan.process_timeout', 240));
        $process->run();

        if (! $process->isSuccessful()) {
            $bin = basename($command[0] ?? 'process');
            throw new RuntimeException(
                "{$bin} failed (exit {$process->getExitCode()}): " . trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }

        return $process->getOutput();
    }

    private function tempDir(): string
    {
        $dir = storage_path('app/temp');

        if (! FileFacade::exists($dir)) {
            FileFacade::makeDirectory($dir, 0777, true);
        }

        return $dir;
    }

    /** A unique, extension-less path inside the scratch dir. */
    private function tempPath(string $prefix): string
    {
        return $this->tempDir() . '/' . $prefix . uniqid();
    }
}
