<?php

namespace App\Services;

/**
 * Keyword search over the officer guide, used by HelpBotController when
 * Gemini can't answer (daily free quota spent, model busy, timeout). The
 * officer still gets the part of the guide that best matches the question,
 * word for word, instead of only an error.
 *
 * The guide is cut into small pieces: each "**Label:**" bullet on its own
 * (with its section heading), and each section's plain text on its own. A
 * piece scores for every question word it contains, rare words counting
 * more than common ones ("archive" beats "file"), and words in a heading or
 * bullet label counting double. Takes the guide it is given, so an officer's
 * search never sees the Super Admin steps the controller left out.
 */
class HelpGuideSearch
{
    /** Below this the best piece is a coincidence, e.g. only "school" matched. */
    private const MIN_SCORE = 1.5;

    private const STOPWORDS = [
        // English
        'a', 'an', 'the', 'i', 'me', 'my', 'we', 'our', 'you', 'your', 'it', 'its', 'is', 'are',
        'was', 'be', 'been', 'am', 'do', 'does', 'did', 'can', 'could', 'should', 'would', 'will',
        'how', 'what', 'where', 'when', 'why', 'who', 'which', 'to', 'of', 'in', 'on', 'at', 'for',
        'from', 'with', 'by', 'and', 'or', 'not', 'no', 'this', 'that', 'these', 'those', 'there',
        'if', 'so', 'as', 'about', 'into', 'up', 'any', 'some', 'please', 'want', 'need', 'way',
        'just', 'also', 'get', 'got', 'have', 'has', 'thing', 'something', 'one', 'still', 'then',
        // Filipino / Taglish
        'paano', 'pano', 'papaano', 'ano', 'saan', 'nasaan', 'bakit', 'sino', 'kailan', 'alin',
        'ang', 'ng', 'nang', 'sa', 'mga', 'na', 'ba', 'po', 'ko', 'mo', 'ako', 'ikaw', 'siya',
        'kami', 'tayo', 'sila', 'namin', 'natin', 'nila', 'niya', 'si', 'ni', 'kay', 'ito', 'iyan',
        'yan', 'yung', 'iyong', 'ung', 'at', 'kung', 'para', 'may', 'mag', 'nag', 'pag', 'kapag',
        'pa', 'din', 'rin', 'lang', 'lamang', 'naman', 'kasi', 'hindi', 'di', 'oo', 'pwede',
        'puwede', 'gusto', 'kailangan', 'dapat', 'ma', 'maka', 'makapag', 'magpa', 'ipa', 'un',
    ];

    /** Question words that mean the same as a word the guide uses. */
    private const SYNONYMS = [
        'remove' => 'delete', 'erase' => 'delete', 'burahin' => 'delete', 'bura' => 'delete',
        'tanggalin' => 'delete', 'alisin' => 'delete',
        'recover' => 'restore', 'undo' => 'restore', 'ibalik' => 'restore', 'bawiin' => 'restore',
        'find' => 'search', 'hanapin' => 'search', 'hanap' => 'search',
        'gumawa' => 'create', 'gawa' => 'create',
        'nakalimutan' => 'forgot', 'nakalimutang' => 'forgot', 'limot' => 'forgot',
        'bago' => 'new', 'bagong' => 'new', 'idagdag' => 'add', 'dagdag' => 'add', 'magdagdag' => 'add',
        'palitan' => 'rename', 'baguhin' => 'edit', 'ayusin' => 'edit',
        'pangalan' => 'name', 'litrato' => 'photo', 'picture' => 'photo', 'larawan' => 'photo',
        'signin' => 'sign',
        'ipakita' => 'public', 'estudyante' => 'student',
        'telepono' => 'phone', 'cellphone' => 'phone', 'cp' => 'phone', 'mobile' => 'phone',
        'basura' => 'trash', 'bin' => 'trash', 'recycle' => 'trash',
        'opisyal' => 'officer',
        'scanner' => 'scan', 'hardcopy' => 'scan', 'papel' => 'paper',
        'ipadala' => 'send', 'padala' => 'send', 'ibahagi' => 'share',
    ];

    /** @var array<int, array{title: string, label: string, text: string, head: array<string, true>, body: array<string, true>, pairs: array<string, true>}> */
    private array $pieces = [];

    /** @var array<string, float> */
    private array $idf = [];

    public function __construct(string $guide)
    {
        $this->pieces = $this->split($guide);

        $df = [];
        foreach ($this->pieces as $piece) {
            foreach (array_keys($piece['head'] + $piece['body']) as $word) {
                $df[$word] = ($df[$word] ?? 0) + 1;
            }
        }

        $n = max(count($this->pieces), 1);
        foreach ($df as $word => $count) {
            $this->idf[$word] = log(1 + $n / $count);
        }
    }

    /**
     * The best-matching piece of the guide, ready to show as a chat reply, or
     * null when nothing in the guide is about the question.
     */
    public function answer(string $question): ?string
    {
        $sequence = $this->words($question);
        $words = array_unique($sequence);
        if (! $words) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($this->pieces as $piece) {
            $score = 0.0;
            $inHead = 0;
            $found = 0;
            foreach ($words as $word) {
                $idf = $this->idf[$word] ?? 0.0;
                $score += $idf * (2 * isset($piece['head'][$word]) + isset($piece['body'][$word]));
                $inHead += isset($piece['head'][$word]);
                $found += isset($piece['head'][$word]) || isset($piece['body'][$word]);
            }

            // One word somewhere in the text is a coincidence ("tell me a
            // joke" vs "where the phone can tell"); it has to be in the
            // heading, or be one of at least two question words found.
            if (! $inHead && $found < 2) {
                continue;
            }

            // Two question words side by side in the guide too ("upload
            // file", "new officer") beat the same words scattered apart.
            foreach (array_keys($this->pairs($sequence)) as $pair) {
                if (isset($piece['pairs'][$pair])) {
                    [$a, $b] = explode(' ', $pair);
                    $score += $this->idf[$a] + $this->idf[$b];
                }
            }

            // On a tie, the longer piece (usually a section's own text over
            // one bullet from elsewhere) gives more of the picture.
            if ($score > $bestScore || ($score === $bestScore && $best && strlen($piece['text']) > strlen($best['text']))) {
                $best = $piece;
                $bestScore = $score;
            }
        }

        if (! $best || $bestScore < self::MIN_SCORE) {
            return null;
        }

        $label = $best['label'] !== '' ? "**{$best['label']}** " : '';

        return "**{$best['title']}**\n\n{$label}{$best['text']}";
    }

    /**
     * Cuts the guide at every "##"/"###" heading (the "#" title and its note
     * about the guide itself are skipped), then each section into its
     * "- **Label:** ..." bullets and its remaining plain text.
     */
    private function split(string $guide): array
    {
        $pieces = [];
        $sections = preg_split('/^(?=#{2,3} )/m', str_replace("\r\n", "\n", $guide));

        foreach ($sections as $section) {
            if (! preg_match('/^(#{2,3}) (.+)\n/', $section, $m)) {
                continue;
            }
            $title = trim($m[2]);
            $body  = substr($section, strlen($m[0]));

            // A bullet or paragraph runs until the next bullet or blank line;
            // join its hard-wrapped lines back into one.
            $plain = [];
            foreach (preg_split('/\n(?=- |\n)/', $body) as $block) {
                $block = preg_replace('/\s*\n\s*/', ' ', trim($block));
                if ($block === '' || $block === '---') {
                    continue;
                }

                if (preg_match('/^- \*\*(.+?)\*\*\s*(.*)$/s', $block, $b)) {
                    $pieces[] = $this->piece($title, $b[1], $b[2]);
                } else {
                    $plain[] = $block;
                }
            }

            // Plain "- " bullets stay one list in the chat bubble.
            if ($plain) {
                $pieces[] = $this->piece($title, '', preg_replace('/\n\n(?=- )/', "\n", implode("\n\n", $plain)));
            }
        }

        return $pieces;
    }

    private function piece(string $title, string $label, string $text): array
    {
        $head = $this->words($title.' '.$label);
        $body = $this->words($text);

        return [
            'title' => $title,
            'label' => $label,
            'text'  => $text,
            'head'  => array_fill_keys($head, true),
            'body'  => array_fill_keys($body, true),
            'pairs' => $this->pairs($head) + $this->pairs($body),
        ];
    }

    /** "upload file" for every two words next to each other. */
    private function pairs(array $words): array
    {
        $pairs = [];
        for ($i = 1; $i < count($words); $i++) {
            $pairs[$words[$i - 1].' '.$words[$i]] = true;
        }

        return $pairs;
    }

    /** Lower-case word stems without the filler words. */
    private function words(string $text): array
    {
        // "log in" is about signing in, not the Activity Logs.
        $text = preg_replace('/\b(log|sign)[\s-]*(in|on)\b/', 'signin', strtolower($text));
        preg_match_all('/[a-z0-9]+/', $text, $m);

        $words = [];
        foreach ($m[0] as $word) {
            $word = self::SYNONYMS[$word] ?? $word;
            if (strlen($word) < 2 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $words[] = $this->stem($word);
        }

        return $words;
    }

    /**
     * Crude English stemming so "uploading", "uploads" and "upload" match,
     * and "archived" / "archives" / "archive". Both sides go through it, so
     * it only has to be consistent, not correct.
     */
    private function stem(string $word): string
    {
        foreach (['ing', 'ion', 'ed', 'es', 'al', 's', 'e'] as $suffix) {
            if ($suffix === 's' && str_ends_with($word, 'ss')) {
                continue;
            }
            if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= 3) {
                return substr($word, 0, -strlen($suffix));
            }
        }

        return $word;
    }
}
