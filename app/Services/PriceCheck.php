<?php

namespace App\Services;

use App\Models\ActivityProposal;
use App\Models\SiteSetting;
use App\Support\BudgetItemName;
use App\Support\SchoolYear;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Unusual price check": each Unit Price in a budget is compared with what
 * approved proposals paid for the same item (same unit, similar name, see
 * BudgetItemName) in this school year and the few before it. Older prices
 * are first brought up to today's value with the Super Admin's yearly
 * inflation rate.
 *
 * The test is a robust z-score (Iglewicz & Hoaglin 1993, as in VisitSpikes):
 * the median and the median absolute deviation of the past prices, so one
 * odd past price can't move the usual range. The spread never counts as
 * less than 10% of the median; otherwise a few equal prices would make the
 * range so narrow that a different store's ordinary price gets flagged.
 * Together that means a price within about 35% of the usual one is never
 * flagged.
 *
 * A flag is a warning only. When the price is right, the officer writes why
 * (the row's price_note), and the proposal page and Reports show it.
 */
class PriceCheck
{
    /** Fewer past prices than this and nothing is said about a row. */
    public const MIN_PRICES = 3;

    /** The robust z-score from which a price is unusual. */
    public const THRESHOLD = 3.5;

    /** The spread never counts as less than this share of the median. */
    private const MIN_SPREAD = 0.10;

    /** 1 / 0.6745: makes the MAD comparable to a standard deviation. */
    private const MAD_SCALE = 1.4826;

    private ?Collection $history = null;

    /**
     * @param  float  $rate  inflation per year, in percent
     * @param  int  $years  how many school years before this one are compared
     */
    public function __construct(private float $rate = 3.0, private int $years = 3)
    {
    }

    public static function fromSettings(): self
    {
        $settings = SiteSetting::current();

        return new self((float) ($settings->price_inflation_rate ?? 3), (int) ($settings->price_compare_years ?: 3));
    }

    public function rate(): float
    {
        return $this->rate;
    }

    public function years(): int
    {
        return $this->years;
    }

    /**
     * Every budget row of a saved proposal, checked against the others.
     *
     * @return array<int, array|null> by item id
     */
    public function forProposal(ActivityProposal $proposal): array
    {
        $rows = $proposal->items->mapWithKeys(fn ($item) => [$item->id => [
            'particular' => $item->particular,
            'unit'       => $item->unit,
            'unit_price' => $item->unit_price,
        ]])->all();

        return $this->check($rows, self::yearOf($proposal->school_year, $proposal->starts_on ?? $proposal->created_at), $proposal->id);
    }

    /**
     * @param  array<int|string, array{particular?: ?string, unit?: ?string, unit_price?: mixed}>  $rows
     * @param  int  $year  the first year of the proposal's school year, e.g. 2026 for 2026-2027
     * @param  int|null  $exclude  the proposal itself, never compared with its own prices
     * @return array<int|string, array|null> by the same keys; null for a row without a name or price
     */
    public function check(array $rows, int $year, ?int $exclude = null): array
    {
        return array_map(fn ($row) => $this->checkOne($row, $year, $exclude), $rows);
    }

    private function checkOne(array $row, int $year, ?int $exclude): ?array
    {
        $price = (float) ($row['unit_price'] ?? 0);
        $words = BudgetItemName::words($row['particular'] ?? '');
        if ($price <= 0 || ! $words) {
            return null;
        }
        $unit = BudgetItemName::unit($row['unit'] ?? '');

        $past = $this->history()
            ->filter(fn ($h) => $h['proposal_id'] !== $exclude
                && $h['unit'] === $unit
                && abs($year - $h['year']) <= $this->years
                && BudgetItemName::same($words, $h['words']))
            ->map(fn ($h) => $h + ['today' => round($h['price'] * (1 + $this->rate / 100) ** ($year - $h['year']), 2)])
            ->sortByDesc('year')
            ->values();

        if ($past->count() < self::MIN_PRICES) {
            return ['state' => 'few', 'count' => $past->count()];
        }

        $values = $past->pluck('today')->all();
        $median = self::median($values);
        $mad = self::median(array_map(fn ($v) => abs($v - $median), $values));
        $scale = max(self::MAD_SCALE * $mad, self::MIN_SPREAD * $median);
        $z = ($price - $median) / $scale;
        $state = match (true) {
            $z >= self::THRESHOLD  => 'high',
            $z <= -self::THRESHOLD => 'low',
            default                => 'usual',
        };

        return [
            'state'     => $state,
            'count'     => count($values),
            'proposals' => $past->pluck('proposal_id')->unique()->count(),
            // For showing: after the inflation adjustment the median has
            // centavos (₱66.95); the nearest peso reads better, except for
            // very cheap items. The z-score above uses the exact one.
            'median'    => $median >= 10 ? round($median) : round($median, 2),
            'low'       => round(max(0, $median - self::THRESHOLD * $scale), 2),
            'high'      => round($median + self::THRESHOLD * $scale, 2),
            'z'         => round($z, 1),
            'summary'   => self::summary($state, $price, $median),
            // The newest few, for "where does the usual price come from".
            'history'   => $past->take(8)->map(fn ($h) => [
                'proposal'    => $h['proposal'],
                'proposal_id' => $h['proposal_id'],
                'school_year' => $h['year'].'-'.($h['year'] + 1),
                'particular'  => $h['particular'],
                'paid'        => $h['price'],
                'today'       => $h['today'],
            ])->all(),
        ];
    }

    /** "2.1× the usual ₱65", "40% above the usual ₱65", "70% below the usual ₱65". */
    public static function summary(string $state, float $price, float $median): string
    {
        $usual = 'the usual '.self::peso($median >= 10 ? round($median) : $median);
        $ratio = $median > 0 ? $price / $median : 0;

        return match ($state) {
            'high'  => $ratio >= 2 ? rtrim(rtrim(number_format($ratio, 1), '0'), '.').'× '.$usual : round(($ratio - 1) * 100).'% above '.$usual,
            'low'   => round((1 - $ratio) * 100).'% below '.$usual,
            default => 'Close to '.$usual,
        };
    }

    /** ₱65 or ₱12.50: whole pesos without the centavos. */
    public static function peso(float $amount): string
    {
        return '₱'.number_format($amount, round($amount, 2) == floor($amount) ? 0 : 2);
    }

    /** The first year of a school year label, else of the school year a date falls in. */
    public static function yearOf(?string $schoolYear, $date = null): int
    {
        if (SchoolYear::isValid($schoolYear)) {
            return (int) substr($schoolYear, 0, 4);
        }

        return (int) substr(SchoolYear::of($date ? Carbon::parse($date) : Carbon::now()), 0, 4);
    }

    /**
     * Every priced budget row of an approved proposal (Approved, Done, or
     * Cancelled after approval) that is not in the Trash: prices that were
     * signed off, unlike drafts.
     */
    private function history(): Collection
    {
        return $this->history ??= DB::table('activity_proposal_items as i')
            ->join('activity_proposals as p', 'p.id', '=', 'i.activity_proposal_id')
            ->whereNotNull('p.approved_at')
            ->whereNull('p.deleted_at')
            ->where('i.unit_price', '>', 0)
            ->get(['i.particular', 'i.unit', 'i.unit_price', 'p.id as proposal_id', 'p.title', 'p.school_year', 'p.starts_on', 'p.created_at'])
            ->map(fn ($row) => [
                'proposal_id' => (int) $row->proposal_id,
                'proposal'    => $row->title,
                'year'        => self::yearOf($row->school_year, $row->starts_on ?? $row->created_at),
                'particular'  => $row->particular,
                'words'       => BudgetItemName::words($row->particular),
                'unit'        => BudgetItemName::unit($row->unit),
                'price'       => (float) $row->unit_price,
            ]);
    }

    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
