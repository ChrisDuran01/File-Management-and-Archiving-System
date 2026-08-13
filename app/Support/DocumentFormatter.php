<?php

namespace App\Support;

class DocumentFormatter
{
    /**
     * Turn plain, typed-out template text into structured document HTML -
     * centered/bold headings and titles, justified WHEREAS/RESOLVED clauses
     * with a hanging indent, and bold-labeled meta lines (Date:, Subject:,
     * To:/From:, etc.) - instead of one flat pre-wrapped block. $text is
     * expected to already be HTML-safe (values escaped by the caller before
     * substitution), so this only wraps it, it never re-escapes it.
     */
    public static function format(string $text): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text));
        $html = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph, "\n");
            if ($paragraph === '') {
                continue;
            }

            $lines = preg_split('/\n/', $paragraph);
            $joined = implode(' ', array_map('trim', $lines));

            if (self::isMetaBlock($lines)) {
                $rows = array_map(fn ($line) => preg_replace(
                    '/^([A-Za-z][A-Za-z \/]*:)\s*/',
                    '<b>$1</b> ',
                    trim($line)
                ), $lines);

                $html .= '<p class="doc-meta">'.implode('<br>', $rows).'</p>';
            } elseif (preg_match('/^(WHEREAS|RESOLVED)\b/i', $joined)) {
                $withBoldLead = preg_replace('/^(WHEREAS|RESOLVED)\b/i', '<b>$1</b>', $joined, 1);
                $html .= '<p class="doc-clause">'.$withBoldLead.'</p>';
            } elseif (preg_match('/^APPROVED\b/i', $joined)) {
                $html .= '<p class="doc-heading">'.$joined.'</p>';
            } elseif (preg_match('/^(RESOLUTION|MEMORANDUM)\s+NO\.?/i', $joined)) {
                $html .= '<p class="doc-heading">'.nl2br(e($paragraph)).'</p>';
            } elseif (self::looksLikeTitle($joined)) {
                $html .= '<p class="doc-title">'.$joined.'</p>';
            } else {
                $html .= '<p class="doc-paragraph">'.$joined.'</p>';
            }
        }

        return $html;
    }

    /**
     * A paragraph counts as a "meta block" (Date:, Subject:, To:/From:, etc.)
     * when every one of its lines has a short "Label:" lead-in - covers both
     * single date/subject lines and multi-line memo headers (To/From/Date/
     * Subject) as one cohesively styled block.
     */
    private static function isMetaBlock(array $lines): bool
    {
        foreach ($lines as $line) {
            if (! preg_match('/^[A-Za-z][A-Za-z \/]{0,20}:\s?/', trim($line))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Heuristic for "this paragraph is a document title": short, and
     * entirely uppercase once punctuation/spacing is stripped out.
     */
    private static function looksLikeTitle(string $text): bool
    {
        $lettersOnly = preg_replace('/[^A-Za-z]/', '', $text);

        return $lettersOnly !== ''
            && $lettersOnly === strtoupper($lettersOnly)
            && strlen($text) <= 200;
    }
}
