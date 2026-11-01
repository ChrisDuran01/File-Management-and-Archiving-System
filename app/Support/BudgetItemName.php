<?php

namespace App\Support;

/**
 * A budget row's Particular and Unit as plain words, so one item typed in
 * different ways counts as the same: "Medals" = "Medal", "Speaker's token"
 * = "Token for speaker", and "Packed lunch" is close enough to "Lunch". The
 * price check (App\Services\PriceCheck) and the "often budgeted together"
 * suggestions (App\Services\BudgetRules) both match items this way.
 */
class BudgetItemName
{
    /**
     * Two names are the same item when at least half of all their words are
     * shared (Jaccard similarity): "Lunch" and "Packed lunch" (1 of 2) are,
     * "Lights and sound" and "Sound system" (1 of 3) are not.
     */
    public const SAME_ITEM = 0.5;

    /** Words that don't tell items apart, English and Filipino. */
    private const STOPWORDS = ['and', 'of', 'for', 'the', 'an', 'with', 'to', 'in', 'on', 'per', 'at', 'by', 'ng', 'sa', 'para', 'mga'];

    /** Unit spellings that mean the same thing. */
    private const UNITS = [
        'pax' => 'pax', 'person' => 'pax', 'head' => 'pax', 'participant' => 'pax', 'people' => 'pax',
        'pc' => 'pc', 'pcs' => 'pc', 'piece' => 'pc',
    ];

    /**
     * The name's words, lower case, singular and sorted, each once.
     *
     * @return string[]
     */
    public static function words(?string $name): array
    {
        $text = preg_replace("/['’]s\b/u", '', mb_strtolower((string) $name));
        $words = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (mb_strlen($word) >= 2 && ! in_array($word, self::STOPWORDS, true)) {
                $words[self::singular($word)] = true;
            }
        }
        $words = array_keys($words);
        sort($words);

        return $words;
    }

    /** One spelling for the item, e.g. "Token for speaker's" -> "speaker token". */
    public static function key(?string $name): string
    {
        return implode(' ', self::words($name));
    }

    /** Shared words over all words of the two names, 0 to 1. */
    public static function similarity(array $a, array $b): float
    {
        if (! $a || ! $b) {
            return 0.0;
        }
        $shared = count(array_intersect($a, $b));

        return $shared / (count($a) + count($b) - $shared);
    }

    public static function same(array $a, array $b): bool
    {
        return self::similarity($a, $b) >= self::SAME_ITEM;
    }

    /** "Pieces", "pcs." and "pc" -> "pc"; "persons" and "pax" -> "pax"; blank -> "". */
    public static function unit(?string $unit): string
    {
        $unit = self::singular(preg_replace('/[^\p{L}]+/u', '', mb_strtolower((string) $unit)));

        return self::UNITS[$unit] ?? $unit;
    }

    /** Plain English plurals only; short words and anything with a digit stay as they are. */
    private static function singular(string $word): string
    {
        if (mb_strlen($word) <= 3 || preg_match('/\d/', $word)) {
            return $word;
        }

        return match (true) {
            str_ends_with($word, 'ies')                   => mb_substr($word, 0, -3).'y',
            (bool) preg_match('/(ss|x|ch|sh)es$/', $word) => mb_substr($word, 0, -2),
            str_ends_with($word, 's') && ! str_ends_with($word, 'ss') => mb_substr($word, 0, -1),
            default                                       => $word,
        };
    }
}
