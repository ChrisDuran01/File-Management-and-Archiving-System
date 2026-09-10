<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Given the rendered + OCR'd pages of one scan batch, proposes where one
 * multi-document PDF should be split into separate documents.
 *
 * Barcode separator sheets are tried first (a page whose barcode decodes
 * exactly to the configured token is a divider). If none are found, or
 * barcode detection is disabled/unavailable, it falls back to detecting
 * visually blank pages as dividers. Divider pages are dropped from every
 * segment. The operator always corrects the result on the review screen,
 * so this only needs to be a good first guess.
 */
class ScanSeparatorDetector
{
    /**
     * @param array<int, array{number:int, image:string, text:string}> $pages
     * @return array{segments: array<int, array{start_page:int, end_page:int}>, dropped_pages: array<int, int>, method: string}
     */
    public function detect(array $pages): array
    {
        ksort($pages);
        $pageNumbers = array_column($pages, 'number');
        $lastPage = $pageNumbers ? max($pageNumbers) : 0;

        if ($lastPage === 0) {
            return ['segments' => [], 'dropped_pages' => [], 'method' => 'none'];
        }

        $method = 'none';
        $separators = [];

        if (config('scan.separation.barcode_enabled')) {
            $separators = $this->barcodeSeparators($pages);
            if ($separators) {
                $method = 'barcode';
            }
        }

        if (! $separators && config('scan.separation.blank_page_enabled')) {
            $separators = $this->blankSeparators($pages);
            if ($separators) {
                $method = 'blank';
            }
        }

        $separatorSet = array_fill_keys($separators, true);
        $segments = [];
        $runStart = null;

        for ($n = 1; $n <= $lastPage; $n++) {
            $isSeparator = isset($separatorSet[$n]);

            if (! $isSeparator && $runStart === null) {
                $runStart = $n;
            }

            if (($isSeparator || $n === $lastPage) && $runStart !== null) {
                $runEnd = $isSeparator ? $n - 1 : $n;
                if ($runEnd >= $runStart) {
                    $segments[] = ['start_page' => $runStart, 'end_page' => $runEnd];
                }
                $runStart = null;
            }
        }

        // Every page was a divider (or none matched) - treat the whole batch
        // as one document rather than filing nothing.
        if (! $segments) {
            return [
                'segments'     => [['start_page' => 1, 'end_page' => $lastPage]],
                'dropped_pages' => [],
                'method'       => 'none',
            ];
        }

        sort($separators);

        return [
            'segments'      => $segments,
            'dropped_pages' => array_values($separators),
            'method'        => $method,
        ];
    }

    /**
     * @param array<int, array{number:int, image:string, text:string}> $pages
     * @return array<int, int>
     */
    private function barcodeSeparators(array $pages): array
    {
        $binary = config('services.ocr.zbarimg_path');
        $token = (string) config('scan.separation.barcode_token');
        $separators = [];

        foreach ($pages as $page) {
            if (! is_file($page['image'])) {
                continue;
            }

            try {
                $process = new Process([$binary, '--quiet', '--raw', $page['image']]);
                $process->setTimeout(30);
                $process->run();
            } catch (\Throwable $e) {
                Log::warning('Scan separator: zbarimg unavailable, skipping barcode detection: ' . $e->getMessage());
                return [];
            }

            // zbarimg exits 4 when it simply found no barcode - not an error.
            if (! $process->isSuccessful() && $process->getExitCode() !== 4) {
                Log::warning('Scan separator: zbarimg failed, skipping barcode detection: ' . trim($process->getErrorOutput()));
                return [];
            }

            foreach (preg_split('/\r\n|\r|\n/', trim($process->getOutput())) as $value) {
                if (trim($value) === $token) {
                    $separators[] = $page['number'];
                    break;
                }
            }
        }

        return $separators;
    }

    /**
     * A page is only treated as a blank divider when it is BOTH visually
     * near-empty AND carries essentially no recognized text - so a sparse
     * but real page (a short cover note, a page with one line) is never
     * mistaken for a separator.
     *
     * @param array<int, array{number:int, image:string, text:string}> $pages
     * @return array<int, int>
     */
    private function blankSeparators(array $pages): array
    {
        $threshold = (float) config('scan.separation.blank_ink_threshold');
        $separators = [];

        foreach ($pages as $page) {
            $hasText = mb_strlen(preg_replace('/\s+/', '', (string) $page['text'])) >= 8;

            if (! $hasText && $this->isVisuallyBlank($page['image'], $threshold)) {
                $separators[] = $page['number'];
            }
        }

        return $separators;
    }

    private function isVisuallyBlank(string $imagePath, float $threshold): bool
    {
        if (! is_file($imagePath)) {
            return false;
        }

        $img = @imagecreatefromstring(file_get_contents($imagePath));
        if (! $img) {
            return false;
        }

        try {
            $small = imagescale($img, 300);
            if ($small !== false) {
                imagedestroy($img);
                $img = $small;
            }

            $w = imagesx($img);
            $h = imagesy($img);
            $total = max(1, $w * $h);
            $dark = 0;

            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $rgb = imagecolorat($img, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $luminance = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
                    if ($luminance < 128) {
                        $dark++;
                    }
                }
            }

            return ($dark / $total) < $threshold;
        } finally {
            imagedestroy($img);
        }
    }
}
