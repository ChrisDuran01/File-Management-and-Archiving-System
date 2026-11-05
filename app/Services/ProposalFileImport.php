<?php

namespace App\Services;

use App\Models\ActivityProposal;
use RuntimeException;

/**
 * Reads a Detailed Activity Proposal from a PDF or a scan (a scanned PDF,
 * or a photo of the paper) into the proposal form, like the Word import.
 *
 * Both come out as text laid out the way the page looks: a PDF's own text
 * through pdftotext -layout, a scan through Tesseract with every word put
 * where it sits on the page (OcrExtractor::layoutText). That text is turned
 * into the paragraphs and tables ProposalWordImport reads from Word, so one
 * set of rules reads every kind of file. A table is found by its header line
 * (Time / Activity / Venue, or Category / Particular / Quantity ...), and
 * each word below it goes to the column whose header it sits closest to.
 *
 * A PDF has no cell borders in its text: a row that wraps onto more lines is
 * joined back by what starts a row (a time; a quantity or price), and which
 * rows a merged Category cell covers is a guess (the rows below it, as Word
 * prints it at the top). A scan also has misread letters. The officer is
 * told to check, and nothing is saved before they do.
 */
class ProposalFileImport
{
    /** Fewer letters than this in a PDF's text: it is a scan, not a text PDF. */
    private const MIN_TEXT_LETTERS = 200;

    /** A proposal is 2 to 6 pages; more is a whole folder scanned at once. */
    private const MAX_PAGES = 12;

    /** Scans are read at this resolution: small print stays readable. */
    private const SCAN_DPI = 300;

    /** Heading-area labels the SG's proposal uses, for lines without a colon. */
    private const HEADING_LABELS = '/^(activity title|title|theme|date|dates|date of activity|proponents?|budget|total budget)$/i';

    public function __construct(
        private readonly ProposalWordImport $reader,
        private readonly PdfToolkit $pdf,
        private readonly OcrExtractor $ocr,
    ) {
    }

    /** @return array the same result as ProposalWordImport::read() */
    public function read(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'docx'               => $this->reader->read($path),
            'pdf'                => $this->readPdf($path),
            'jpg', 'jpeg', 'png' => $this->fromLayoutText($this->ocr->layoutText($path), 'scan'),
            default              => throw new RuntimeException('Choose a Word file (.docx), a PDF, or a photo (JPG or PNG) of the proposal.'),
        };
    }

    private function readPdf(string $path): array
    {
        $pages = $this->pdf->pageCount($path);
        if ($pages === 0) {
            throw new RuntimeException('This PDF could not be opened. Open it on a computer, save it again, and retry.');
        }
        if ($pages > self::MAX_PAGES) {
            throw new RuntimeException('This PDF has '.$pages.' pages. A proposal is usually 2 to 6; upload only the proposal\'s pages.');
        }

        $text = $this->pdf->layoutText($path);
        if (mb_strlen(preg_replace('/\s+/u', '', $text)) >= self::MIN_TEXT_LETTERS) {
            return $this->fromLayoutText($text, 'PDF');
        }

        // No text layer: a scanned PDF. Read each page as a photo.
        $images = $this->pdf->rasterizeAllPages($path, self::SCAN_DPI);
        try {
            $text = implode("\f", array_map(fn (string $image) => $this->ocr->layoutText($image), $images));
        } finally {
            foreach ($images as $image) {
                @unlink($image);
            }
        }

        return $this->fromLayoutText($text, 'scan');
    }

    /**
     * Reads laid-out text (pages split by "\f"). Public so the reading can
     * be tested without pdftotext or Tesseract.
     *
     * @param  string  $source  "PDF" or "scan", for the messages
     */
    public function fromLayoutText(string $text, string $source): array
    {
        if (mb_strlen(preg_replace('/\s+/u', '', $text)) < 40) {
            throw new RuntimeException($source === 'scan'
                ? 'No text could be read from this scan. Take the photo straight on, in good light, with the whole page in view.'
                : 'This PDF has no text to read.');
        }

        $result = $this->reader->fromBlocks($this->blocks($text), $source);

        if ($result['items']) {
            $result['warnings'][] = 'Check the Category of each budget row: a '.$source.' doesn\'t show which rows a merged Category cell covers.';
        }
        if ($source === 'scan') {
            array_unshift($result['warnings'], 'Read from a scan: some letters and numbers may be misread. Check every part, the budget most of all.');
        }

        return $result;
    }

    // ------------------------------------------------------------ blocks

    /**
     * The laid-out text as ProposalWordImport::fromBlocks() reads it:
     * paragraphs (['type' => 'p', 'text', 'list']) and tables (['type' =>
     * 'table', 'rows' => cells]).
     *
     * @return array<int, array>
     */
    public function blocks(string $text): array
    {
        $lines = $this->lines($text);
        $width = max(array_map(fn ($l) => mb_strlen(trim($l)), $lines) ?: [1]);

        $blocks = [];
        $para = null;
        $flush = function () use (&$para, &$blocks) {
            if ($para !== null) {
                $blocks[] = ['type' => 'p', 'text' => $para['text'], 'list' => $para['list']];
                $para = null;
            }
        };

        $section = 'heading';
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $raw = $lines[$i];
            $line = $this->collapse($raw);
            if ($line === '') {
                $flush();

                continue;
            }

            if ($part = $this->reader->sectionOf($line)) {
                $flush();
                $blocks[] = ['type' => 'p', 'text' => $line, 'list' => false];
                $section = $part;

                continue;
            }

            // The signatories, after the schedule or budget: the rest of the page.
            if (in_array($section, ['schedule', 'budget'], true) && $this->labelTokens($raw)) {
                $flush();
                array_push($blocks, ...$this->signatoryBlocks(array_slice($lines, $i)));

                break;
            }

            if ($kind = $this->tableHeader($raw)) {
                $flush();
                [$table, $i] = $this->table($lines, $i, $kind);
                $blocks[] = $table;
                $i--; // the for loop steps to the first line after the table

                continue;
            }

            // Title, Theme, Date ...: one per line, "Label: value".
            if ($section === 'heading') {
                $flush();
                $blocks[] = ['type' => 'p', 'text' => $this->headingLine($raw), 'list' => false];

                continue;
            }

            $item = preg_match('/^(?:\(?(?:\d{1,2}|[a-z])[\.\)]|[•●▪◦\-–])\s+/u', $line);
            $ended = $para !== null
                && preg_match('/[\.!\?:]["”\')]?$/u', $para['last'])
                && mb_strlen($para['last']) < $width * 0.85;

            if ($para === null || $item || $ended) {
                $flush();
                $para = [
                    'text' => preg_replace('/^[•●▪◦\-–]\s+/u', '', $line),
                    'list' => (bool) $item,
                    'last' => $line,
                ];
            } else {
                // A line wrapped from the one above.
                $para['text'] .= ' '.$line;
                $para['last'] = $line;
            }
        }
        $flush();

        return $blocks;
    }

    /**
     * The lines of every page, without what repeats at the top or bottom of
     * the pages (a letterhead, a footer) and without page numbers.
     *
     * @return array<int, string>
     */
    private function lines(string $text): array
    {
        $pages = array_map(
            fn ($page) => preg_split('/\R/u', rtrim($page)),
            explode("\f", str_replace("\r", '', $text)),
        );

        $edges = [];
        foreach ($pages as $p => $lines) {
            $filled = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));
            foreach (array_unique(array_merge(array_slice($filled, 0, 6), array_slice($filled, -4))) as $line) {
                $edges[$this->collapse($line)][$p] = true;
            }
        }
        $repeated = count($pages) > 1
            ? array_keys(array_filter($edges, fn ($onPages) => count($onPages) >= 2))
            : [];

        $out = [];
        foreach ($pages as $lines) {
            foreach ($lines as $line) {
                $plain = $this->collapse($line);
                if (in_array($plain, $repeated, true) && $plain !== '') {
                    continue;
                }
                if (preg_match('/^(page\s+)?\d{1,3}(\s+of\s+\d{1,3})?$/i', $plain)) {
                    continue;
                }
                $out[] = rtrim($line);
            }
        }

        return $out;
    }

    /** "Title          FRESHIVAL 2026" (a two-column heading table) => "Title: FRESHIVAL 2026". */
    private function headingLine(string $raw): string
    {
        $tokens = $this->tokens($raw);
        if (count($tokens) >= 2 && preg_match(self::HEADING_LABELS, rtrim($tokens[0]['text'], ' :'))) {
            $value = implode(' ', array_map(fn ($t) => $t['text'], array_slice($tokens, 1)));

            return rtrim($tokens[0]['text'], ' :').': '.ltrim($value, ': ');
        }

        return $this->collapse($raw);
    }

    // ------------------------------------------------------------- tables

    /** "schedule" or "budget" when the line is a table's header row. */
    private function tableHeader(string $raw): ?string
    {
        $heads = array_map(fn ($t) => strtolower($t['text']), $this->tokens($raw));
        if (count($heads) < 2) {
            return null;
        }
        $has = fn (string $pattern) => (bool) array_filter($heads, fn ($h) => preg_match($pattern, $h));

        return match (true) {
            $has('/^time\b/') && $has('/activit|program/')                   => 'schedule',
            $has('/particular|^items?\b|description/') && $has('/qty|quantit|price|cost|amount/') => 'budget',
            default => null,
        };
    }

    /**
     * Reads the table whose header row is $lines[$start]. Every word goes to
     * the column whose header it sits closest to; a line that doesn't start
     * a new row continues the one above (wrapped text).
     *
     * @return array{0: array, 1: int} the table block, and the first line after it
     */
    private function table(array $lines, int $start, string $kind): array
    {
        $headers = $this->tokens($lines[$start]);
        $i = $start + 1;

        // A header that wraps ("Unit" over "Price"): words under it, no numbers.
        for ($k = 0; $k < 2 && isset($lines[$i]); $k++) {
            $tokens = $this->tokens($lines[$i]);
            $headerWords = $tokens && ! preg_match('/\d/', $lines[$i]) && ! array_filter($tokens,
                fn ($t) => ! preg_match('/^(price|cost|total|amount|unit|qty|quantity|no\.?|personnel|venue|charge|in-charge|person|responsible|\/)$/i', $t['text']));
            if (! $headerWords) {
                break;
            }
            foreach ($tokens as $t) {
                $c = $this->column($t, $headers);
                $headers[$c]['text'] .= ' '.$t['text'];
            }
            $i++;
        }

        $headerTexts = array_map(fn ($h) => strtolower($h['text']), $headers);
        $col = fn (string $pattern) => array_key_first(array_filter($headerTexts, fn ($h) => preg_match($pattern, $h)));
        $numberCols = $kind === 'budget'
            ? array_filter([$col('/qty|quantit/'), $col('/price|cost/'), $col('/total|amount/')], fn ($c) => $c !== null)
            : [];
        $firstCol = $kind === 'schedule' ? ($col('/^time\b/') ?? 0) : null;

        $rows = [];
        $row = null;
        $added = [];   // what the open row got, newest last: [column, text, from a wrapped line]
        $emcee = null;
        $count = count($lines);

        $close = function () use (&$row, &$rows, &$added) {
            if ($row !== null) {
                $rows[] = $row;
            }
            $row = null;
            $added = [];
        };

        for (; $i < $count; $i++) {
            $raw = $lines[$i];
            $line = $this->collapse($raw);
            if ($line === '') {
                continue;
            }
            // The header repeated at the top of the next page is skipped.
            if ($this->tableHeader($raw) === $kind) {
                continue;
            }
            if ($this->reader->sectionOf($line) || $this->labelTokens($raw) || $this->tableHeader($raw)) {
                break;
            }

            // The Master of Ceremony row, across the whole table; its name
            // may be printed above the words (as Word centres them).
            if ($kind === 'schedule' && preg_match('/master\s+of\s+ceremon/i', $line)) {
                $names = [];
                while ($added && end($added)[2] && $this->reader->looksLikeName(end($added)[1])) {
                    [$c, $text] = array_pop($added);
                    array_unshift($names, $text);
                    array_pop($row[$c]);
                }
                $after = trim(preg_replace('/master\s+of\s+ceremon(?:y|ies)\s*:?/i', '', $line));
                if ($after !== '') {
                    $names[] = $after;
                }
                for ($j = $i + 1; $j < $count && $this->reader->looksLikeName($this->collapse($lines[$j])) && ! $this->labelTokens($lines[$j]); $j++) {
                    $names[] = $this->collapse($lines[$j]);
                    $i = $j;
                }
                $close();
                $emcee = $names;

                continue;
            }

            $cells = [];
            foreach ($this->tokens($raw) as $t) {
                $cells[$this->column($t, $headers)][] = $t['text'];
            }
            $cells = array_map(fn ($parts) => implode(' ', $parts), $cells);

            $total = $kind === 'budget' && preg_match('/^\s*(grand\s+)?total\b/i', $line);
            $startsRow = match ($kind) {
                'schedule' => isset($cells[$firstCol]),
                'budget'   => $total || (bool) array_filter($numberCols, fn ($c) => isset($cells[$c]) && preg_match('/\d/', $cells[$c])),
            };

            $wrapped = ! ($startsRow || $row === null);
            if (! $wrapped) {
                $close();
                $row = array_fill(0, count($headers), []);
            }
            foreach ($cells as $c => $text) {
                $row[$c][] = $text;
                $added[] = [$c, $text, $wrapped];
            }

            if ($total) {
                $close();
                $i++;

                break;
            }
        }
        $close();

        $cell = fn (string $text) => ['text' => $text, 'continued' => false, 'span' => 1];
        $tableRows = [array_map(fn ($h) => $cell($h['text']), $headers)];
        foreach ($rows as $r) {
            $tableRows[] = array_map(fn ($parts) => $cell(implode("\n", $parts)), $r);
        }
        if ($emcee !== null) {
            $tableRows[] = [$cell(implode("\n", array_merge(['Master of Ceremony'], $emcee)))];
        }

        return [['type' => 'table', 'rows' => $tableRows], $i];
    }

    /** The header a word belongs under: the closest by left edge, right edge or middle. */
    private function column(array $token, array $headers): int
    {
        $best = 0;
        $bestDistance = PHP_INT_MAX;
        foreach ($headers as $c => $h) {
            $distance = min(
                abs($token['start'] - $h['start']),
                abs($token['end'] - $h['end']),
                abs(($token['start'] + $token['end']) - ($h['start'] + $h['end'])) / 2,
            );
            if ($distance < $bestDistance) {
                $best = $c;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    // -------------------------------------------------------- signatories

    /**
     * The signatories, written out as Word paragraphs ("Noted:", then
     * "NAME\nPosition" per person) for ProposalWordImport::signatories().
     * On the page two or three people often stand side by side under one
     * label, so a position goes to the name above it that it is closest to,
     * and a person to the label closest above them.
     *
     * @return array<int, array>
     */
    private function signatoryBlocks(array $lines): array
    {
        $blocks = [];
        $labels = [];   // the labels of the band being read: [middle, group]
        $people = [];   // [line, middle, group, name, position]

        $group = null;
        $flush = function () use (&$people, &$blocks, &$group) {
            usort($people, fn ($a, $b) => [$a['line'], $a['x']] <=> [$b['line'], $b['x']]);
            foreach ($people as $p) {
                // The label on a line of its own, as the reader finds it.
                if ($p['group'] !== $group) {
                    $group = $p['group'];
                    $blocks[] = ['type' => 'p', 'text' => ActivityProposal::SIGNATORY_GROUPS[$group].':', 'list' => false];
                }
                $blocks[] = ['type' => 'p', 'text' => trim($p['name']."\n".$p['position']), 'list' => false];
            }
            $people = [];
        };

        foreach ($lines as $n => $raw) {
            $line = $this->collapse($raw);
            if ($line === '' || preg_match('/^[_\-\s\.]+$/', $line) || preg_match('/^(date|signature)\s*:?/i', $line)) {
                continue;
            }

            if ($found = $this->labelTokens($raw)) {
                $flush();
                $labels = $found;

                continue;
            }
            if (! $labels) {
                continue;
            }

            foreach ($this->tokens($raw) as $t) {
                $x = ($t['start'] + $t['end']) / 2;
                if ($this->reader->looksLikeName($t['text'])) {
                    $people[] = ['line' => $n, 'x' => $x, 'group' => $this->nearest($labels, $x)['group'], 'name' => $t['text'], 'position' => ''];

                    continue;
                }
                // A position: under the closest name (the lowest one, when
                // names are stacked); a second line of it joins the first.
                $k = null;
                foreach ($people as $j => $p) {
                    if ($p['line'] >= $n) {
                        continue;
                    }
                    $dx = abs($p['x'] - $x);
                    if ($k === null || $dx < abs($people[$k]['x'] - $x) - 4
                        || (abs($dx - abs($people[$k]['x'] - $x)) <= 4 && $p['line'] > $people[$k]['line'])) {
                        $k = $j;
                    }
                }
                if ($k !== null) {
                    $people[$k]['position'] = trim($people[$k]['position'].' '.$t['text']);
                }
            }
        }
        $flush();

        return $blocks;
    }

    /** Signatory labels on a line ("Noted:", "Approved:"), each with its middle. */
    private function labelTokens(string $raw): array
    {
        $labels = [];
        foreach ($this->tokens($raw) as $t) {
            if (($group = $this->reader->signatoryGroup($t['text'])) !== null) {
                $labels[] = ['x' => ($t['start'] + $t['end']) / 2, 'group' => $group];
            }
        }

        return $labels;
    }

    private function nearest(array $items, float $x): array
    {
        usort($items, fn ($a, $b) => abs($a['x'] - $x) <=> abs($b['x'] - $x));

        return $items[0];
    }

    // -------------------------------------------------------------- text

    /**
     * The runs of a line that two or more spaces keep apart (a column, or a
     * cell), with where each starts and ends, counted in characters.
     *
     * @return array<int, array{text: string, start: int, end: int}>
     */
    private function tokens(string $raw): array
    {
        preg_match_all('/\S+(?: \S+)*/u', $raw, $matches, PREG_OFFSET_CAPTURE);

        return array_map(function ($m) use ($raw) {
            $start = mb_strlen(substr($raw, 0, $m[1]));

            return ['text' => $m[0], 'start' => $start, 'end' => $start + mb_strlen($m[0])];
        }, $matches[0]);
    }

    private function collapse(string $raw): string
    {
        return trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $raw));
    }
}
