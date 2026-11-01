<?php

namespace App\Services;

use App\Models\ActivityProposal;
use Carbon\Carbon;

/**
 * The Reports "Proposals" tab: the budget data mining over Activity
 * Proposals. Two panels, each a short title and a computed answer like the
 * other tabs:
 * - Unusual prices: every budget row of the period's proposals that the
 *   price check flags, with the officer's reason when there is one.
 * - Often budgeted together: the strongest association rules over all
 *   approved proposals (the period doesn't apply: the more budgets, the
 *   better the rules).
 *
 * Plain Eloquent and PHP, so unlike the other tabs it runs on SQLite too.
 */
class ProposalReport
{
    public function build(Carbon $from, Carbon $to): array
    {
        $prices = PriceCheck::fromSettings();
        $rules = new BudgetRules;

        // A proposal belongs to the period by its activity date; one
        // without a date yet, by when it was made.
        $proposals = ActivityProposal::with('items')
            ->where(fn ($q) => $q->whereBetween('starts_on', [$from->toDateString(), $to->toDateString()])
                ->orWhere(fn ($q) => $q->whereNull('starts_on')->whereBetween('created_at', [$from, $to])))
            ->orderBy('starts_on')
            ->get();

        $flagged = [];
        $priced = 0;
        $compared = 0;
        foreach ($proposals as $proposal) {
            $checks = $prices->forProposal($proposal);
            foreach ($proposal->items as $item) {
                $check = $checks[$item->id] ?? null;
                if ($check === null) {
                    continue;
                }
                $priced++;
                if ($check['state'] === 'few') {
                    continue;
                }
                $compared++;
                if (in_array($check['state'], ['high', 'low'], true)) {
                    $flagged[] = [
                        'proposal'   => $proposal,
                        'item'       => $item,
                        'check'      => $check,
                        'reason'     => $item->price_note,
                    ];
                }
            }
        }

        $mined = $rules->mine();

        return [
            'prices' => [
                'answer'    => $this->pricesAnswer($proposals->count(), $priced, $compared, $flagged),
                'flagged'   => $flagged,
                'proposals' => $proposals->count(),
                'priced'    => $priced,
                'compared'  => $compared,
                'rate'      => $prices->rate(),
                'years'     => $prices->years(),
            ],
            'rules' => [
                'answer'  => $this->rulesAnswer($mined, $rules),
                'baskets' => $mined['baskets'],
                'rules'   => array_map(fn ($rule) => $rule + [
                    'if_label'   => $rules->ifLabel($rule),
                    'then_label' => $rules->label($rule['then']),
                ], array_slice($mined['rules'], 0, 10)),
            ],
        ];
    }

    private function pricesAnswer(int $proposals, int $priced, int $compared, array $flagged): string
    {
        if ($proposals === 0) {
            return 'No proposals in this period.';
        }
        if ($compared === 0) {
            return 'None of the '.$priced.' '.($priced === 1 ? 'price' : 'prices').' could be compared yet: an item needs '
                .PriceCheck::MIN_PRICES.' or more prices in approved proposals.';
        }

        $coverage = ' ('.$compared.' of '.$priced.' prices had enough approved proposals to compare with)';
        if (! $flagged) {
            return '<strong>No unusual prices</strong>: every price compared is close to what approved proposals paid'.$coverage.'.';
        }

        $count = count($flagged);
        $inProposals = collect($flagged)->pluck('proposal.id')->unique()->count();
        $withReason = collect($flagged)->filter(fn ($f) => filled($f['reason']))->count();
        $reasons = match (true) {
            $withReason === $count => $count === 1 ? 'it has the officer’s reason' : 'all have the officer’s reason',
            $withReason === 0      => $count === 1 ? 'it has no reason from the officer yet' : 'none has a reason from the officer yet',
            default                => $withReason.' '.($withReason === 1 ? 'has' : 'have').' the officer’s reason',
        };

        return '<strong>'.$count.' '.($count === 1 ? 'price looks' : 'prices look').' unusual</strong> in '
            .$inProposals.' '.($inProposals === 1 ? 'proposal' : 'proposals').'; '.$reasons.$coverage.'.';
    }

    private function rulesAnswer(array $mined, BudgetRules $rules): string
    {
        $n = $mined['baskets'];
        if ($n < BudgetRules::MIN_PROPOSALS) {
            return 'Needs at least '.BudgetRules::MIN_PROPOSALS.' approved proposals to find items that go together; there '
                .($n === 1 ? 'is 1' : 'are '.$n).'.';
        }
        if (! $mined['rules']) {
            return 'No items go together often enough yet in the '.$n.' approved proposals.';
        }

        $top = $mined['rules'][0];

        return 'Most common pattern: <strong>'.$top['together'].' of the '.$top['with_if'].'</strong> approved proposals with '
            .e($rules->ifLabel($top)).' also had '.e($rules->label($top['then'])).' ('.count($mined['rules']).' '
            .(count($mined['rules']) === 1 ? 'pattern' : 'patterns').' in '.$n.' approved proposals).';
    }
}
