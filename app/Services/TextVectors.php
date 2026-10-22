<?php

namespace App\Services;

/**
 * Turns a document's extracted text into a TF-IDF vector and compares two
 * of them by cosine similarity. Shared by DuplicateDetector (one file
 * against the rest) and ArchiveInsights (every file against every other).
 *
 * A file's word counts are stored once (files.text_terms); the IDF part
 * depends on the whole collection, so weights are worked out when compared.
 */
class TextVectors
{
    /** Only the start of each document is needed to characterise it. */
    public const MAX_TEXT_CHARS = 20000;

    /** Fewer words than this and there is too little to compare. */
    public const MIN_TOKENS = 15;

    private const STOPWORDS = [
        'the','and','for','that','this','with','from','are','was','were','been','have','has','had','will','shall',
        'not','but','you','your','our','their','his','her','its','who','whom','which','what','when','where','also',
        'any','all','can','may','such','than','then','them','these','those','there','here','upon','per','page',
        'ang','ng','sa','mga','na','at','ay','para','ito','may','ni','kay','nang','pa','din','rin','po','ko','mo',
        'dated','date','copy','signed','name','office','university','republic','philippines',
    ];

    /**
     * Word => count for a document, or null when it has too few words to
     * judge (a blank scan, a photo with a caption).
     *
     * @return array<string, int>|null
     */
    public function termCounts(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $tokens = $this->tokenize(mb_substr($text, 0, self::MAX_TEXT_CHARS));

        return count($tokens) < self::MIN_TOKENS ? null : array_count_values($tokens);
    }

    /** @return string[] lower-cased words of 3+ letters, minus stopwords and plain numbers */
    public function tokenize(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $stop = array_flip(self::STOPWORDS);

        return array_values(array_filter($words, fn ($w) => mb_strlen($w) >= 3
            && ! isset($stop[$w])
            && ! ctype_digit($w)));
    }

    /**
     * How many documents contain each word.
     *
     * @param  iterable<array<string, int>>  $allCounts
     * @return array<string, int>
     */
    public function documentFrequencies(iterable $allCounts): array
    {
        $df = [];
        foreach ($allCounts as $counts) {
            foreach (array_keys($counts) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }

        return $df;
    }

    /**
     * TF-IDF weights, scaled to length 1 so a dot product of two vectors is
     * their cosine similarity:  w = (1 + ln tf) * (ln((N + 1) / (df + 1)) + 1)
     *
     * @param  array<string, int>  $counts
     * @param  array<string, int>  $df
     * @return array<string, float>
     */
    public function weigh(array $counts, array $df, int $n): array
    {
        $v = [];
        foreach ($counts as $term => $c) {
            $v[$term] = (1 + log($c)) * (log(($n + 1) / (($df[$term] ?? 0) + 1)) + 1);
        }

        return $this->normalize($v);
    }

    /** @return array<string, float> */
    public function normalize(array $v): array
    {
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v)));
        if ($norm <= 0) {
            return $v;
        }
        foreach ($v as $k => $x) {
            $v[$k] = $x / $norm;
        }

        return $v;
    }

    /** Dot product over the smaller vector's terms. */
    public function dot(array $a, array $b): float
    {
        if (count($a) > count($b)) {
            [$a, $b] = [$b, $a];
        }
        $s = 0.0;
        foreach ($a as $term => $w) {
            if (isset($b[$term])) {
                $s += $w * $b[$term];
            }
        }

        return $s;
    }
}
