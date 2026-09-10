<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Best-effort extraction of the structured fields (title, type, reference
 * number, date, sender, recipient) from a document's OCR text, used to
 * pre-fill the review form. Everything here is a guess the operator
 * confirms or overwrites - nothing is trusted blindly.
 */
class DocumentFieldParser
{
    private const TYPE_KEYWORDS = [
        'MEMORANDUM'       => 'Memo',
        'MEMO'             => 'Memo',
        'RESOLUTION'       => 'Resolution',
        'NOTICE'           => 'Notice',
        'INVOICE'          => 'Invoice',
        'OFFICIAL RECEIPT' => 'Receipt',
        'O.R.'             => 'Receipt',
        'CONTRACT'         => 'Contract',
        'AGREEMENT'        => 'Contract',
        'REPORT'           => 'Report',
        'LETTER'           => 'Letter',
    ];

    /**
     * @return array{title: ?string, document_type: ?string, reference_no: ?string, document_date: ?string, sender: ?string, recipient: ?string}
     */
    public function parse(?string $text): array
    {
        $text = trim((string) $text);

        return [
            'title'         => $this->guessTitle($text),
            'document_type' => $this->guessType($text),
            'reference_no'  => $this->guessReferenceNo($text),
            'document_date' => $this->guessDate($text),
            'sender'        => $this->guessLabelled($text, ['FROM']),
            'recipient'     => $this->guessLabelled($text, ['TO']),
        ];
    }

    private function guessType(string $text): ?string
    {
        $haystack = mb_strtoupper($text);

        foreach (self::TYPE_KEYWORDS as $needle => $type) {
            if (str_contains($haystack, $needle)) {
                return $type;
            }
        }

        return null;
    }

    private function guessReferenceNo(string $text): ?string
    {
        $patterns = [
            '/\b(?:Ref(?:erence)?\.?\s*(?:No\.?|Number)?|Control\s*No\.?|Series\s*(?:of\s*\d{4}\s*)?No\.?)\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{2,})/i',
            '/\bNo\.?\s*([0-9]{2,4}[-\/][0-9]{1,5})\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return trim($m[1], " .,-/");
            }
        }

        return null;
    }

    private function guessDate(string $text): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        // Prefer a line that mentions "Date"; otherwise scan every line.
        usort($lines, fn ($a, $b) => (int) (stripos($b, 'date') !== false) <=> (int) (stripos($a, 'date') !== false));

        $candidates = [
            '/\b(\d{4}-\d{2}-\d{2})\b/',
            '/\b(\d{1,2}\/\d{1,2}\/\d{2,4})\b/',
            '/\b((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})\b/i',
            '/\b(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})\b/i',
        ];

        foreach ($lines as $line) {
            foreach ($candidates as $pattern) {
                if (preg_match($pattern, $line, $m)) {
                    try {
                        return Carbon::parse($m[1])->toDateString();
                    } catch (\Throwable $e) {
                        // not a real date - keep looking
                    }
                }
            }
        }

        return null;
    }

    /**
     * Text following a "LABEL:" line start, e.g. "FROM:" / "TO:" / "SUBJECT:".
     *
     * @param array<int, string> $labels
     */
    private function guessLabelled(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/^\s*' . preg_quote($label, '/') . '\s*:\s*(.+)$/mi', $text, $m)) {
                $value = trim($m[1]);
                if ($value !== '') {
                    return Str::limit($value, 200, '');
                }
            }
        }

        return null;
    }

    private function guessTitle(string $text): ?string
    {
        $subject = $this->guessLabelled($text, ['SUBJECT', 'SUBJ', 'RE']);
        if ($subject) {
            return $subject;
        }

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                return Str::limit(preg_replace('/\s+/', ' ', $line), 80, '');
            }
        }

        return null;
    }
}
