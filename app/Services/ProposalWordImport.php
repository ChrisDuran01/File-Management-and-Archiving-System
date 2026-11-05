<?php

namespace App\Services;

use App\Models\ActivityProposal;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Reads a Detailed Activity Proposal written in Word (.docx) into the
 * proposal form: the heading fields (Title, Theme, Date, Proponent), parts
 * I-V and the signatories, laid out like the SG's own proposal. Tables are
 * read cell by cell from the document's XML, so the budget keeps its
 * columns; merged Category cells are carried down to the rows they span.
 * PDFs and scans come in through ProposalFileImport, which turns them into
 * the same blocks (fromBlocks).
 *
 * Nothing is saved here. The result fills a new proposal that the officer
 * checks; rows whose Total Price in the file doesn't equal Quantity x Unit
 * Price, and a Grand Total that doesn't add up, are reported.
 */
class ProposalWordImport
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Bigger than any real proposal; guards against a zip bomb. */
    private const MAX_XML_BYTES = 20 * 1024 * 1024;

    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private DOMXPath $xpath;

    /** What the file is called in the messages: "Word file", "PDF", "scan". */
    private string $source = 'Word file';

    /**
     * @return array{
     *     fields: array<string, mixed>,
     *     items: array<int, array{category: string, particular: string, quantity: string, unit: string, unit_price: string}>,
     *     checks: array<int, string>,
     *     found: array<string, int>,
     *     warnings: array<int, string>
     * }
     */
    public function read(string $path): array
    {
        return $this->fromBlocks($this->blocks($this->documentXml($path)));
    }

    /**
     * Reads the proposal from its body in reading order: paragraphs
     * (['type' => 'p', 'text', 'list']) and tables (['type' => 'table',
     * 'rows' => cells of ['text', 'continued', 'span']]), as blocks() makes
     * them from Word. ProposalFileImport builds the same blocks from a PDF
     * or a scan, so every kind of file is read by the one set of rules.
     */
    public function fromBlocks(array $blocks, string $source = 'Word file'): array
    {
        $this->source = $source;
        $warnings = [];

        $sections = $this->splitSections($blocks);
        $fields = $this->headingFields($sections['heading'], $warnings);

        $fields['rationale'] = $this->paragraphs($sections['rationale']);
        [$fields['objectives_intro'], $fields['objectives']] = $this->objectives($sections['objectives']);
        $fields['methods'] = $this->paragraphs($sections['methods']);
        [$fields['schedule'], $fields['master_of_ceremony']] = $this->schedule($sections['schedule'], $warnings);
        [$items, $checks, $fileGrand] = $this->budget($sections['budget'], $warnings);
        $fields['signatories'] = $this->signatories($sections['signatories']);

        $sum = round(array_sum(array_map(fn ($i) => (float) $i['quantity'] * (float) $i['unit_price'], $items)), 2);
        foreach (array_filter([$fileGrand, $fields['_budget'] ?? null], fn ($v) => $v !== null) as $stated) {
            if (abs($stated - $sum) > 0.009) {
                $warnings[] = 'The '.$this->source.' says the budget is '.ActivityProposal::peso($stated).', but the rows add up to '
                    .ActivityProposal::peso($sum).'. Check the budget rows.';
                break;
            }
        }
        unset($fields['_budget']);

        foreach (['rationale' => 'I. Rationale', 'methods' => 'III. Methods & Approaches'] as $key => $label) {
            if ($fields[$key] === null) {
                $warnings[] = 'No '.$label.' was found.';
            }
        }
        if (! $fields['objectives'] && ! $fields['objectives_intro']) {
            $warnings[] = 'No II. Objectives were found.';
        }

        $people = array_sum(array_map('count', $fields['signatories']));

        return [
            'fields'   => $fields,
            'items'    => $items,
            'checks'   => $checks,
            'found'    => [
                'objectives'  => count($fields['objectives']),
                'schedule'    => count($fields['schedule']),
                'budget'      => count($items),
                'signatories' => $people,
            ],
            'warnings' => $warnings,
        ];
    }

    // ------------------------------------------------------------ reading

    private function documentXml(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('This file could not be opened as a Word (.docx) file.');
        }
        $stat = $zip->statName('word/document.xml');
        if (! $stat) {
            $zip->close();
            throw new RuntimeException('This doesn\'t look like a Word (.docx) file. For a .doc file, open it in Word and Save As .docx.');
        }
        if ($stat['size'] > self::MAX_XML_BYTES) {
            $zip->close();
            throw new RuntimeException('This Word file is too large to read.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return (string) $xml;
    }

    /**
     * The body in reading order: paragraphs as text (and whether they are a
     * Word list item) and tables as rows of cells. A cell continued from the
     * one above (a vertical merge) is marked so the reader can carry it down.
     *
     * @return array<int, array>
     */
    private function blocks(string $xml): array
    {
        $dom = new DOMDocument();
        // LIBXML_NONET: never fetch anything; entities are not expanded.
        if ($xml === '' || ! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('This Word file could not be read. Open it in Word, save it again, and retry.');
        }
        $this->xpath = new DOMXPath($dom);
        $this->xpath->registerNamespace('w', self::W);

        $body = $this->xpath->query('/w:document/w:body')->item(0);
        if (! $body) {
            throw new RuntimeException('This Word file has no text to read.');
        }

        $blocks = [];
        $this->collect($body, $blocks);

        return $blocks;
    }

    private function collect(DOMElement $parent, array &$blocks): void
    {
        foreach ($parent->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
                continue;
            }
            if ($node->localName === 'p') {
                $blocks[] = ['type' => 'p', 'text' => $this->paragraphText($node), 'list' => $this->isList($node)];
            } elseif ($node->localName === 'tbl') {
                $blocks[] = ['type' => 'table', 'rows' => $this->tableRows($node)];
            } elseif ($node->localName === 'sdt') {
                // Content controls wrap ordinary paragraphs and tables.
                foreach ($this->xpath->query('w:sdtContent', $node) as $content) {
                    $this->collect($content, $blocks);
                }
            }
        }
    }

    private function paragraphText(DOMElement $p): string
    {
        $text = '';
        foreach ($this->xpath->query('.//w:t|.//w:tab|.//w:br|.//w:cr', $p) as $el) {
            // Text inside a nested paragraph (a text box) belongs to that paragraph.
            if ($this->closestParagraph($el) !== $p) {
                continue;
            }
            $text .= match ($el->localName) {
                't'     => $el->textContent,
                'tab'   => "\t",
                default => "\n",
            };
        }

        return trim(preg_replace('/[ \x{00A0}]+/u', ' ', $text));
    }

    private function closestParagraph(DOMElement $el): ?DOMElement
    {
        for ($n = $el->parentNode; $n; $n = $n->parentNode) {
            if ($n instanceof DOMElement && $n->localName === 'p' && $n->namespaceURI === self::W) {
                return $n;
            }
        }

        return null;
    }

    private function isList(DOMElement $p): bool
    {
        return $this->xpath->query('w:pPr/w:numPr', $p)->length > 0;
    }

    /** @return array<int, array<int, array{text: string, continued: bool, span: int}>> */
    private function tableRows(DOMElement $tbl): array
    {
        $rows = [];
        foreach ($this->xpath->query('w:tr', $tbl) as $tr) {
            $cells = [];
            foreach ($this->xpath->query('w:tc', $tr) as $tc) {
                $lines = [];
                foreach ($this->xpath->query('w:p', $tc) as $p) {
                    $line = $this->paragraphText($p);
                    if ($line !== '') {
                        $lines[] = $line;
                    }
                }
                $vMerge = $this->xpath->query('w:tcPr/w:vMerge', $tc)->item(0);
                $span = $this->xpath->query('w:tcPr/w:gridSpan', $tc)->item(0);
                $cells[] = [
                    'text'      => implode("\n", $lines),
                    'continued' => $vMerge instanceof DOMElement && $vMerge->getAttributeNS(self::W, 'val') !== 'restart',
                    'span'      => $span instanceof DOMElement ? max(1, (int) $span->getAttributeNS(self::W, 'val')) : 1,
                ];
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    // ----------------------------------------------------------- sections

    /**
     * Splits the blocks at the part headings: "I. Rationale", "II.
     * Objectives", "III. Methods & Approaches", "IV. Schedule of Activities",
     * "V. Budgetary Requirements", then the signatories after the budget.
     */
    private function splitSections(array $blocks): array
    {
        $sections = array_fill_keys(['heading', 'rationale', 'objectives', 'methods', 'schedule', 'budget', 'signatories'], []);
        $current = 'heading';

        foreach ($blocks as $block) {
            if ($block['type'] === 'p') {
                if ($section = $this->sectionOf($block['text'])) {
                    $current = $section;

                    continue;
                }
                // The signatories start at their first label, after the budget.
                if (in_array($current, ['budget', 'schedule'], true) && $this->signatoryGroup($block['text']) !== null) {
                    $current = 'signatories';
                }
            } elseif ($current === 'budget' && $this->hasBudgetTable($sections['budget']) && $this->tableHasSignatoryLabel($block)) {
                $current = 'signatories';
            }
            $sections[$current][] = $block;
        }

        return $sections;
    }

    /** The part a heading line starts ("IV. Schedule of Activities" => schedule), or null. */
    public function sectionOf(string $text): ?string
    {
        // The roman numeral, also as a scan misreads it ("Ul." for II., "Ml" for III.).
        $t = strtolower(trim(preg_replace('/^(?:[IVX]+\s*[\.\)]\s*|\S{1,4}[\.\)]\s+|[IVXLM1l|]{1,4}\s*[,:]?\s+)/i', '', $text)));
        if (mb_strlen($t) > 60) {
            return null; // a sentence that mentions the word, not a heading
        }

        return match (true) {
            (bool) preg_match('/^rationale\b/', $t) => 'rationale',
            (bool) preg_match('/^(general |specific )?objectives?\b/', $t) => 'objectives',
            (bool) preg_match('/^(methods?|methodology)\b/', $t) => 'methods',
            (bool) preg_match('/^(schedule|time and schedule|program of activities|time table|timetable)\b/', $t) => 'schedule',
            (bool) preg_match('/^budget(ary)? requirements?\b/', $t) => 'budget',
            default => null,
        };
    }

    private function hasBudgetTable(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if ($block['type'] === 'table') {
                return true;
            }
        }

        return false;
    }

    private function tableHasSignatoryLabel(array $block): bool
    {
        foreach ($block['rows'] as $row) {
            foreach ($row as $cell) {
                foreach (explode("\n", $cell['text']) as $line) {
                    if ($this->signatoryGroup($line) !== null) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    // ------------------------------------------------------------ heading

    /** Title, Theme, Date, Proponent (and the stated Budget, to check the rows). */
    private function headingFields(array $blocks, array &$warnings): array
    {
        $pairs = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'p') {
                foreach (explode("\n", $block['text']) as $line) {
                    if (preg_match('/^\s*([A-Za-z ]{3,20}?)\s*:\s*(.+)$/', $line, $m)) {
                        $pairs[] = [$m[1], $m[2]];
                    }
                }
            } else {
                foreach ($block['rows'] as $row) {
                    $texts = array_values(array_filter(array_map(fn ($c) => $c['text'], $row), fn ($t) => $t !== ''));
                    if (count($texts) >= 2) {
                        $pairs[] = [rtrim($texts[0], " :\t"), $texts[1]];
                    } elseif (count($texts) === 1 && preg_match('/^\s*([A-Za-z ]{3,20}?)\s*:\s*(.+)$/s', $texts[0], $m)) {
                        $pairs[] = [$m[1], $m[2]];
                    }
                }
            }
        }

        $fields = ['title' => null, 'theme' => null, 'starts_on' => null, 'ends_on' => null, 'proponent' => null, '_budget' => null];
        foreach ($pairs as [$label, $value]) {
            $value = trim(preg_replace('/\s+/', ' ', $value));
            $label = strtolower(trim($label));
            if ($value === '') {
                continue;
            }
            if (preg_match('/^(activity )?title$/', $label) && $fields['title'] === null) {
                $fields['title'] = mb_substr($value, 0, 255);
            } elseif ($label === 'theme' && $fields['theme'] === null) {
                $fields['theme'] = mb_substr($value, 0, 255);
            } elseif (preg_match('/^(date|dates|date of activity|inclusive dates?)$/', $label) && $fields['starts_on'] === null) {
                [$start, $end] = $this->dates($value);
                if ($start) {
                    $fields['starts_on'] = $start;
                    $fields['ends_on'] = $end;
                } else {
                    $warnings[] = 'The date "'.$value.'" could not be read. Set it by hand.';
                }
            } elseif (preg_match('/^proponents?$/', $label) && $fields['proponent'] === null) {
                $fields['proponent'] = mb_substr($value, 0, 255);
            } elseif (preg_match('/^(total )?budget$/', $label) && $fields['_budget'] === null) {
                $fields['_budget'] = $this->amount($value);
            }
        }

        if ($fields['title'] === null) {
            $warnings[] = 'No "Title:" was found. Type the title before saving.';
        }

        return $fields;
    }

    /**
     * "October 22, 2026", "November 6-8, 2026", "November 30 - December 2,
     * 2026", "December 30, 2026 - January 2, 2027" (any dash, or "to").
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function dates(string $text): array
    {
        $t = strtolower(preg_replace('/\s*(?:–|—|-|\bto\b|\buntil\b)\s*/u', '-', $text));
        $t = preg_replace('/\s+/', ' ', trim(str_replace('.', '', $t)));
        $month = '(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*';

        $make = function ($m, $d, $y): ?string {
            $mon = self::MONTHS[substr($m, 0, 3)] ?? null;

            return $mon && checkdate($mon, (int) $d, (int) $y) ? sprintf('%04d-%02d-%02d', $y, $mon, $d) : null;
        };

        if (preg_match("/^$month (\d{1,2}),? (\d{4})-$month (\d{1,2}),? (\d{4})$/", $t, $m)) {
            return [$make($m[1], $m[2], $m[3]), $make($m[4], $m[5], $m[6])];
        }
        if (preg_match("/^$month (\d{1,2})-$month (\d{1,2}),? (\d{4})$/", $t, $m)) {
            return [$make($m[1], $m[2], $m[5]), $make($m[3], $m[4], $m[5])];
        }
        if (preg_match("/^$month (\d{1,2})-(\d{1,2}),? (\d{4})$/", $t, $m)) {
            return [$make($m[1], $m[2], $m[4]), $make($m[1], $m[3], $m[4])];
        }
        if (preg_match("/^$month (\d{1,2}),? (\d{4})$/", $t, $m)) {
            return [$make($m[1], $m[2], $m[3]), null];
        }
        try {
            return [Carbon::parse($text)->format('Y-m-d'), null];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    // -------------------------------------------------------------- parts

    private function paragraphs(array $blocks): ?string
    {
        $lines = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'p' && $block['text'] !== '') {
                $lines[] = $block['text'];
            }
        }

        return $lines ? mb_substr(implode("\n", $lines), 0, 20000) : null;
    }

    /**
     * Text before the list is the introduction ("The celebration aims
     * to:"); Word list items, and lines starting "1." or "a)", are the
     * objectives.
     *
     * @return array{0: ?string, 1: array<int, string>}
     */
    private function objectives(array $blocks): array
    {
        $intro = [];
        $list = [];
        foreach ($blocks as $block) {
            $lines = $block['type'] === 'p'
                ? [[$block['text'], $block['list']]]
                : array_map(fn ($t) => [$t, true], $this->tableTexts($block));
            foreach ($lines as [$text, $isList]) {
                if ($text === '') {
                    continue;
                }
                if (preg_match('/^\(?(\d{1,2}|[a-z])[\.\)]\s+(.+)$/s', $text, $m)) {
                    $list[] = $m[2];
                } elseif ($isList || $list) {
                    $list[] = $text;
                } else {
                    $intro[] = $text;
                }
            }
        }

        return [$intro ? implode("\n", $intro) : null, array_map(fn ($o) => mb_substr($o, 0, 500), array_slice($list, 0, 30))];
    }

    private function tableTexts(array $table): array
    {
        $texts = [];
        foreach ($table['rows'] as $row) {
            foreach ($row as $cell) {
                if ($cell['text'] !== '') {
                    $texts[] = $cell['text'];
                }
            }
        }

        return $texts;
    }

    /**
     * The Schedule of Activities table: Time, Activity, Venue/Personnel, and
     * the Master of Ceremony row that spans the table at the bottom.
     *
     * @return array{0: array<int, array{time: string, activity: string, personnel: string}>, 1: ?string}
     */
    private function schedule(array $blocks, array &$warnings): array
    {
        $table = $this->firstTable($blocks);
        if (! $table) {
            if ($blocks) {
                $warnings[] = 'The Schedule of Activities is not in a table, so it was not filled in. Copy it by hand.';
            }

            return [[], null];
        }

        $cols = ['time' => 0, 'activity' => 1, 'personnel' => 2];
        $start = 0;
        foreach ($table['rows'] as $i => $row) {
            $heads = array_map(fn ($c) => strtolower($c['text']), $row);
            if (array_filter($heads, fn ($h) => str_contains($h, 'time'))) {
                foreach ($heads as $j => $h) {
                    if (str_contains($h, 'time')) {
                        $cols['time'] = $j;
                    } elseif (str_contains($h, 'activit') || str_contains($h, 'program')) {
                        $cols['activity'] = $j;
                    } elseif (preg_match('/venue|person|charge|responsible|facilitator/', $h)) {
                        $cols['personnel'] = $j;
                    }
                }
                $start = $i + 1;
                break;
            }
        }

        $rows = [];
        $emcee = null;
        foreach (array_slice($table['rows'], $start) as $row) {
            $filled = array_values(array_filter($row, fn ($c) => $c['text'] !== '' && ! $c['continued']));
            $all = implode("\n", array_map(fn ($c) => $c['text'], $row));
            if (preg_match('/master\s+of\s+ceremon/i', $all) && count($filled) <= 1) {
                $names = array_filter(array_map('trim', preg_split('/\R/', $all)), fn ($l) => $l !== '' && ! preg_match('/master\s+of\s+ceremon/i', $l));
                $emcee = mb_substr(implode(' & ', $names), 0, 255) ?: null;

                continue;
            }
            $cell = fn ($key) => isset($row[$cols[$key]]) && ! $row[$cols[$key]]['continued'] ? $row[$cols[$key]]['text'] : '';
            $entry = [
                'time'      => mb_substr($cell('time'), 0, 60),
                'activity'  => mb_substr($cell('activity'), 0, 2000),
                'personnel' => mb_substr($cell('personnel'), 0, 500),
            ];
            if ($entry['time'] !== '' || $entry['activity'] !== '' || $entry['personnel'] !== '') {
                $rows[] = $entry;
            }
        }

        return [array_slice($rows, 0, 80), $emcee];
    }

    /**
     * The Budgetary Requirements table, by its header names: Category,
     * Particular, Quantity, Unit, Unit Price, Total Price. A merged or blank
     * Category takes the one above it. The file's own Total Price of each
     * row and its Grand Total are kept only to check the numbers.
     *
     * @return array{0: array, 1: array<int, string>, 2: ?float}
     */
    private function budget(array $blocks, array &$warnings): array
    {
        $table = $this->firstTable($blocks);
        if (! $table) {
            $warnings[] = 'No budget table was found under V. Budgetary Requirements.';

            return [[], [], null];
        }

        $cols = null;
        $start = 0;
        foreach ($table['rows'] as $i => $row) {
            $heads = array_map(fn ($c) => strtolower(preg_replace('/\s+/', ' ', $c['text'])), $this->expand($row));
            if (array_filter($heads, fn ($h) => preg_match('/particular|item|description/', $h))) {
                $cols = [];
                foreach ($heads as $j => $h) {
                    $key = match (true) {
                        str_contains($h, 'categor') => 'category',
                        (bool) preg_match('/particular|item|description/', $h) => 'particular',
                        (bool) preg_match('/qty|quantit|^no\.?$/', $h) => 'quantity',
                        (bool) preg_match('/unit (price|cost)|price per|cost per/', $h) => 'unit_price',
                        (bool) preg_match('/^unit/', $h) => 'unit',
                        (bool) preg_match('/total|amount/', $h) => 'total',
                        (bool) preg_match('/price|cost/', $h) => 'unit_price',
                        default => null,
                    };
                    if ($key && ! isset($cols[$key])) {
                        $cols[$key] = $j;
                    }
                }
                $start = $i + 1;
                break;
            }
        }
        if ($cols === null || ! isset($cols['particular'])) {
            $warnings[] = 'The budget table has no "Particular" column, so its rows were not filled in.';

            return [[], [], null];
        }

        $items = [];
        $checks = [];
        $grand = null;
        $category = '';
        foreach (array_slice($table['rows'], $start) as $row) {
            $cells = $this->expand($row, true);
            $get = fn ($key) => isset($cols[$key], $cells[$cols[$key]]) ? $cells[$cols[$key]] : ['text' => '', 'continued' => false];
            $joined = strtolower(implode(' ', array_map(fn ($c) => $c['text'], $cells)));

            if (preg_match('/grand\s*total|^\s*total\b/', $joined)) {
                $numbers = array_filter(array_map(fn ($c) => $this->amount($c['text']), $cells), fn ($n) => $n !== null);
                $grand = $numbers ? (float) end($numbers) : null;

                continue;
            }

            $particular = trim(preg_replace('/\s+/', ' ', $get('particular')['text']));
            $cat = $get('category');
            if ($cat['continued'] || ($cat['text'] === '' && $particular !== '')) {
                $catText = $category; // merged (or left blank) under the one above
            } else {
                $catText = trim(preg_replace('/\s+/', ' ', $cat['text']));
                $category = $catText;
            }

            $qtyText = $get('quantity')['text'];
            $qty = $this->amount($qtyText);
            $unit = trim($get('unit')['text']);
            // "60 pax" in the Quantity cell: the number, then the unit.
            if ($unit === '' && preg_match('/^\s*[\d,\.]+\s+([A-Za-z\.]+)\s*$/', $qtyText, $m)) {
                $unit = $m[1];
            }
            $price = $this->amount($get('unit_price')['text']);
            $total = $this->amount($get('total')['text']);

            if ($particular === '' && $qty === null && $price === null && $total === null) {
                continue;
            }
            if ($price === null && $total !== null && $qty) {
                $price = round($total / $qty, 2);
            }
            if ($qty === null && $total !== null && $price) {
                $qty = round($total / $price, 2);
            }

            $index = count($items);
            $items[] = [
                'category'   => mb_substr($catText, 0, 100),
                'particular' => mb_substr($particular, 0, 255),
                'quantity'   => $qty === null ? '' : $this->plain($qty),
                'unit'       => mb_substr($unit, 0, 30),
                'unit_price' => $price === null ? '' : $this->plain($price),
            ];

            if ($qty !== null && $price !== null && $total !== null && abs($qty * $price - $total) > 0.009) {
                $checks[$index] = 'The '.$this->source.' says '.ActivityProposal::peso($total).', but '.$this->plain($qty).' × '
                    .ActivityProposal::peso($price).' = '.ActivityProposal::peso($qty * $price).'. Check this row.';
            } elseif ($particular === '' || $qty === null || $price === null) {
                $checks[$index] = 'Part of this row could not be read. Fill in the empty cells.';
            }
        }

        return [array_slice($items, 0, 150), $checks, $grand];
    }

    /**
     * Cells laid out on the table grid: a cell spanning two columns fills
     * both, so the header names line up with the data cells below them.
     */
    private function expand(array $row, bool $blankSpan = false): array
    {
        $out = [];
        foreach ($row as $cell) {
            $out[] = $cell;
            for ($k = 1; $k < $cell['span']; $k++) {
                $out[] = $blankSpan ? ['text' => '', 'continued' => false, 'span' => 1] : $cell;
            }
        }

        return $out;
    }

    private function firstTable(array $blocks): ?array
    {
        foreach ($blocks as $block) {
            if ($block['type'] === 'table') {
                return $block;
            }
        }

        return null;
    }

    // -------------------------------------------------------- signatories

    /**
     * After each label ("Prepared by:", "Noted:", "Budget Available:",
     * "Recommending Approval:", "Approved:") come the people: a name, then
     * the position on the next line. In a table, each cell is one person.
     *
     * @return array<string, array<int, array{name: string, position: string}>>
     */
    private function signatories(array $blocks): array
    {
        $groups = array_fill_keys(array_keys(ActivityProposal::SIGNATORY_GROUPS), []);
        $group = null;
        $person = null;

        $flush = function () use (&$person, &$group, &$groups) {
            if ($person && $group && ($person['name'] !== '' || $person['position'] !== '') && count($groups[$group]) < 10) {
                $groups[$group][] = ['name' => mb_substr($person['name'], 0, 120), 'position' => mb_substr($person['position'], 0, 150)];
            }
            $person = null;
        };

        $take = function (string $line) use (&$person, &$group, $flush) {
            $line = trim($line);
            if ($line === '' || preg_match('/^[_\-\s\.]+$/', $line) || preg_match('/^(date|signature)\s*:?/i', $line)) {
                return;
            }
            if (($g = $this->signatoryGroup($line)) !== null) {
                $flush();
                $group = $g;

                return;
            }
            if (! $group) {
                return;
            }
            if ($this->looksLikeName($line) && (! $person || $person['position'] !== '' || $person['name'] !== '')) {
                $flush();
                $person = ['name' => $line, 'position' => ''];
            } elseif ($person && $person['position'] === '') {
                $person['position'] = $line;
            } else {
                $flush();
                $person = ['name' => '', 'position' => $line];
            }
        };

        foreach ($blocks as $block) {
            if ($block['type'] === 'p') {
                foreach (explode("\n", $block['text']) as $line) {
                    $take($line);
                }

                continue;
            }
            foreach ($block['rows'] as $row) {
                foreach ($row as $cell) {
                    foreach (explode("\n", $cell['text']) as $line) {
                        $take($line);
                    }
                    $flush(); // one person per cell
                }
            }
        }
        $flush();

        return $groups;
    }

    public function signatoryGroup(string $line): ?string
    {
        $t = strtolower(trim($line, " \t:"));

        return match (true) {
            (bool) preg_match('/^prepared( by)?$/', $t) => 'prepared',
            (bool) preg_match('/^noted( by)?$/', $t) => 'noted',
            (bool) preg_match('/^(budget available|funds? available)$/', $t) => 'budget',
            (bool) preg_match('/^recommending approval$/', $t) => 'recommending',
            (bool) preg_match('/^approved( by)?$/', $t) => 'approved',
            default => null,
        };
    }

    /** Names are printed in capitals on the SG's proposal ("JUAN DELA CRUZ"). */
    public function looksLikeName(string $line): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $line);
        if (mb_strlen($letters) < 3) {
            return false;
        }

        return mb_strtoupper($letters) === $letters;
    }

    // -------------------------------------------------------------- numbers

    /** "₱ 1,200.00", "P1200", "Php 60", "1,200" -> 1200.0; "", "-" -> null. */
    private function amount(string $text): ?float
    {
        $t = str_replace([',', ' ', "\u{00A0}"], '', $text);
        if (! preg_match('/(\d+(?:\.\d+)?)/', $t, $m)) {
            return null;
        }

        return (float) $m[1];
    }

    private function plain(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
