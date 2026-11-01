<?php

namespace App\Services;

use App\Support\BudgetItemName;
use Illuminate\Support\Facades\DB;

/**
 * "Often budgeted together": association rules mined from the budgets of
 * approved proposals. This is market-basket analysis - each proposal's
 * budget is a basket of items - with the Apriori algorithm (Agrawal &
 * Srikant 1994): find every set of items that at least MIN_TOGETHER
 * budgets had, growing the sets one item at a time and only from sets that
 * were already frequent, then turn each set into rules "if a budget has X,
 * it also has Y".
 *
 * Each rule has the usual three measures:
 * - support: the share of all budgets that had X and Y,
 * - confidence: of the budgets with X, the share that also had Y,
 * - lift: confidence over how common Y is anyway; above 1, X and Y go
 *   together more often than chance.
 *
 * While a budget is being written, the rules whose X it already has suggest
 * the Y it doesn't have yet.
 */
class BudgetRules
{
    /** Below this many approved proposals, nothing is mined: the rules would mean little. */
    public const MIN_PROPOSALS = 5;

    /** A set of items counts when at least this many budgets had all of them. */
    public const MIN_TOGETHER = 2;

    public const MIN_CONFIDENCE = 0.6;

    /** Sets of up to 3 items, so rules up to "X and Y -> Z". */
    private const MAX_SIZE = 3;

    private ?array $mined = null;

    /**
     * @return array{
     *     baskets: int,
     *     items: array<string, array{label: string, category: ?string, unit: ?string, count: int}>,
     *     rules: array<int, array{if: string[], then: string, together: int, with_if: int, support: float, confidence: float, lift: float}>
     * }
     */
    public function mine(): array
    {
        if ($this->mined !== null) {
            return $this->mined;
        }

        $rows = DB::table('activity_proposal_items as i')
            ->join('activity_proposals as p', 'p.id', '=', 'i.activity_proposal_id')
            ->whereNotNull('p.approved_at')
            ->whereNull('p.deleted_at')
            ->get(['i.activity_proposal_id', 'i.particular', 'i.category', 'i.unit']);

        // Each budget as a set of item keys; each key with the spelling,
        // category and unit officers used most for it.
        $baskets = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = BudgetItemName::key($row->particular);
            if ($key === '') {
                continue;
            }
            $baskets[$row->activity_proposal_id][$key] = true;
            foreach (['label' => $row->particular, 'category' => $row->category, 'unit' => $row->unit] as $field => $value) {
                $value = trim((string) $value);
                $seen[$key][$field][$value] = ($seen[$key][$field][$value] ?? 0) + 1;
            }
        }
        $baskets = array_values(array_map(fn ($set) => array_keys($set), $baskets));
        $n = count($baskets);

        // The spelling used most; on a tie, the shorter one.
        $mostUsed = function (array $spellings): ?string {
            uksort($spellings, fn ($a, $b) => $spellings[$b] <=> $spellings[$a] ?: strlen((string) $a) <=> strlen((string) $b));
            $first = (string) array_key_first($spellings);

            return $first === '' ? null : $first;
        };
        $items = [];
        foreach ($seen as $key => $s) {
            $items[$key] = [
                'label'    => $mostUsed($s['label']) ?? $key,
                'category' => $mostUsed($s['category']),
                'unit'     => $mostUsed($s['unit']),
                'count'    => 0,
            ];
        }
        foreach ($baskets as $basket) {
            foreach ($basket as $key) {
                $items[$key]['count']++;
            }
        }

        $this->mined = ['baskets' => $n, 'items' => $items, 'rules' => []];
        if ($n < self::MIN_PROPOSALS) {
            return $this->mined;
        }

        $frequent = $this->frequentSets($baskets);
        $countOf = fn (array $set) => $frequent[count($set)][implode('|', $set)] ?? 0;

        $rules = [];
        foreach ($frequent as $size => $sets) {
            if ($size < 2) {
                continue;
            }
            foreach ($sets as $id => $together) {
                $set = explode('|', $id);
                foreach ($set as $then) {
                    $if = array_values(array_diff($set, [$then]));
                    $withIf = $countOf($if);
                    $confidence = $together / $withIf;
                    $lift = $confidence / ($items[$then]['count'] / $n);
                    if ($confidence >= self::MIN_CONFIDENCE && $lift > 1) {
                        $rules[] = [
                            'if'         => $if,
                            'then'       => $then,
                            'together'   => $together,
                            'with_if'    => $withIf,
                            'support'    => round($together / $n, 3),
                            'confidence' => round($confidence, 3),
                            'lift'       => round($lift, 2),
                        ];
                    }
                }
            }
        }
        // A longer rule says nothing new when a shorter one with the same
        // result is at least as sure: "Certificates and Trophies -> Medals"
        // goes when "Trophies -> Medals" already holds as often.
        $rules = array_values(array_filter($rules, function ($rule) use ($rules) {
            foreach ($rules as $shorter) {
                if ($shorter['then'] === $rule['then'] && count($shorter['if']) < count($rule['if'])
                    && ! array_diff($shorter['if'], $rule['if']) && $shorter['confidence'] >= $rule['confidence']) {
                    return false;
                }
            }

            return true;
        }));

        // Most proposals behind it first: with few budgets, a rule seen in 2
        // of them can have a high lift by chance.
        usort($rules, fn ($a, $b) => [$b['together'], $b['confidence'], $b['lift'], count($a['if'])]
            <=> [$a['together'], $a['confidence'], $a['lift'], count($b['if'])]);

        $this->mined['rules'] = $rules;

        return $this->mined;
    }

    /**
     * Apriori: the sets of 1, 2 and 3 items that at least MIN_TOGETHER
     * budgets had, with how many had them. A set of k items is only counted
     * when every k-1 items of it were already frequent.
     *
     * @param  array<int, string[]>  $baskets
     * @return array<int, array<string, int>> by set size, then "a|b|c" (sorted keys) => budgets
     */
    private function frequentSets(array $baskets): array
    {
        $counts = [];
        foreach ($baskets as $basket) {
            foreach ($basket as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        $frequent = [1 => array_filter($counts, fn ($c) => $c >= self::MIN_TOGETHER)];
        ksort($frequent[1]);
        $sets = array_map(fn ($key) => [$key], array_keys($frequent[1]));
        $baskets = array_map('array_flip', $baskets);

        for ($size = 2; $size <= self::MAX_SIZE && count($sets) > 1; $size++) {
            // Join: two frequent sets that differ only in their last item.
            $known = array_flip(array_map(fn ($s) => implode('|', $s), $sets));
            $candidates = [];
            foreach ($sets as $i => $a) {
                foreach (array_slice($sets, $i + 1) as $b) {
                    if (array_slice($a, 0, -1) !== array_slice($b, 0, -1)) {
                        continue;
                    }
                    $set = array_merge($a, [end($b)]);
                    sort($set);
                    // Prune: every smaller set inside it must be frequent too.
                    $allFrequent = true;
                    foreach ($set as $drop) {
                        if (! isset($known[implode('|', array_values(array_diff($set, [$drop])))])) {
                            $allFrequent = false;
                            break;
                        }
                    }
                    if ($allFrequent) {
                        $candidates[implode('|', $set)] = $set;
                    }
                }
            }

            $frequent[$size] = [];
            foreach ($candidates as $id => $set) {
                $together = 0;
                foreach ($baskets as $basket) {
                    if (count(array_intersect_key(array_flip($set), $basket)) === count($set)) {
                        $together++;
                    }
                }
                if ($together >= self::MIN_TOGETHER) {
                    $frequent[$size][$id] = $together;
                }
            }
            ksort($frequent[$size]);
            $sets = array_map(fn ($id) => explode('|', $id), array_keys($frequent[$size]));
        }

        return $frequent;
    }

    /**
     * What a budget being written may be missing: for each item it doesn't
     * have, the strongest rule whose "if" items it has. A rule applies only
     * when its "if" items are in the budget under the same name ("Medal" =
     * "Medals"; "Lunch" is not "Officials' lunch", which comes with sports
     * awards). An item counts as already there when a row's name is merely
     * close (BudgetItemName::same), so "Trophy (champion)" stops the
     * suggestion of "Trophies".
     *
     * @param  string[]  $particulars  the budget's Particulars so far
     * @return array<int, array{label: string, category: ?string, unit: ?string, text: string, confidence: float}>
     */
    public function suggest(array $particulars, int $limit = 5): array
    {
        $mined = $this->mine();
        if (! $mined['rules']) {
            return [];
        }

        $have = array_values(array_filter(array_map([BudgetItemName::class, 'words'], $particulars)));
        $keys = array_flip(array_map(fn ($words) => implode(' ', $words), $have));
        $memo = [];
        $close = function (string $key) use ($have, &$memo) {
            return $memo[$key] ??= collect($have)->contains(fn ($words) => BudgetItemName::same($words, explode(' ', $key)));
        };

        $best = [];
        foreach ($mined['rules'] as $rule) {
            if ($close($rule['then']) || ! collect($rule['if'])->every(fn ($key) => isset($keys[$key]))) {
                continue;
            }
            // The surest rule; on a tie, the one more proposals back, then
            // the simpler one ("Medals" rather than "Officials' lunch and Medals").
            $current = $best[$rule['then']] ?? null;
            if (! $current || [$rule['confidence'], $rule['together'], -count($rule['if'])] > [$current['confidence'], $current['together'], -count($current['if'])]) {
                $best[$rule['then']] = $rule;
            }
        }
        usort($best, fn ($a, $b) => [$b['confidence'], $b['together'], $b['lift']] <=> [$a['confidence'], $a['together'], $a['lift']]);

        return array_map(fn ($rule) => [
            'label'      => $mined['items'][$rule['then']]['label'],
            'category'   => $mined['items'][$rule['then']]['category'],
            'unit'       => $mined['items'][$rule['then']]['unit'],
            'text'       => $this->because($rule),
            'confidence' => $rule['confidence'],
        ], array_slice($best, 0, $limit));
    }

    /** The names of a rule's "if" items: "Medals", "Medals and Certificates". */
    public function ifLabel(array $rule): string
    {
        $labels = array_map(fn ($key) => $this->mine()['items'][$key]['label'], $rule['if']);

        return implode(' and ', $labels);
    }

    public function label(string $key): string
    {
        return $this->mine()['items'][$key]['label'] ?? $key;
    }

    /** "4 of the 5 approved proposals with Medals had it too." */
    public function because(array $rule): string
    {
        return $rule['together'].' of the '.$rule['with_if'].' approved proposals with '.$this->ifLabel($rule).' had it too.';
    }
}
