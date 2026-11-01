<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\EngagementEvent;
use App\Models\File;
use Carbon\Carbon;

/**
 * Days when noticeably more students than usual opened the student page,
 * and what was posted or opened that day, so the SG can see what drew
 * students in.
 *
 * Each day is compared with the 28 days before it by the modified z-score
 * (Iglewicz & Hoaglin, 1993): how far the day's visits are from the median
 * day, measured in median absolute deviations. The median keeps an earlier
 * spike or quiet weekends from shifting "usual" much. A day counts as
 * unusual at a score of 3.5 or more, with at least 10 visits and at least
 * twice the usual, so a jump from 2 to 5 visits on a quiet page is not
 * reported.
 *
 * Only aggregate daily counts are used; nothing here can tell visitors apart.
 */
class VisitSpikes
{
    public const BASELINE_DAYS = 28;

    /** Fewer recorded days before it and a day can't be called unusual. */
    public const MIN_HISTORY_DAYS = 14;

    public const THRESHOLD = 3.5;

    public const MIN_VISITS = 10;

    public const MIN_TIMES_USUAL = 2.0;

    /**
     * @return array{spikes: array<int, array>, checked: int, usual: ?int, answer: string}
     */
    public function find(Carbon $from, Carbon $to, ?Carbon $since): array
    {
        $empty = ['spikes' => [], 'checked' => 0, 'usual' => null];
        if ($since === null) {
            return $empty + ['answer' => 'Nothing recorded yet. Statistics start the first time a student opens the student page.'];
        }

        $since = $since->copy()->startOfDay();
        $first = $from->copy()->startOfDay()->max($since->copy()->addDays(self::MIN_HISTORY_DAYS));
        $last = $to->copy()->startOfDay()->min(Carbon::today());
        if ($first->gt($last)) {
            return $empty + ['answer' => 'Needs at least 2 weeks of recorded visits before a day can be called unusual. Recording began '.$since->format('M j, Y').'.'];
        }

        $daily = $this->dailyVisits($first->copy()->subDays(self::BASELINE_DAYS), $last->copy()->endOfDay());

        $spikes = [];
        $checkedVisits = [];
        for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
            $baseline = [];
            for ($back = self::BASELINE_DAYS; $back >= 1; $back--) {
                $d = $day->copy()->subDays($back);
                if ($d->gte($since)) {
                    // A recorded day with no visits counts as 0: quiet days are part of "usual".
                    $baseline[] = $daily[$d->toDateString()] ?? 0;
                }
            }
            if (count($baseline) < self::MIN_HISTORY_DAYS) {
                continue;
            }

            $visits = $daily[$day->toDateString()] ?? 0;
            $checkedVisits[] = $visits;
            $usual = $this->median($baseline);
            $score = ($visits - $usual) / $this->spread($baseline, $usual);

            if ($score >= self::THRESHOLD
                && $visits >= self::MIN_VISITS
                && $visits >= self::MIN_TIMES_USUAL * max($usual, 1)) {
                $spike = [
                    'date'   => $day->copy(),
                    'visits' => $visits,
                    'usual'  => (int) round($usual),
                    'times'  => $usual > 0 ? round($visits / $usual, 1) : null,
                    'score'  => round($score, 1),
                ] + $this->whatHappened($day);
                $spike['cause'] = $this->cause($spike);
                $spikes[] = $spike;
            }
        }

        $checked = count($checkedVisits);
        $usual = $checked ? (int) round($this->median($checkedVisits)) : null;

        // Most recent first in the list; the answer names the biggest.
        usort($spikes, fn ($a, $b) => $b['date'] <=> $a['date']);

        return ['spikes' => array_slice($spikes, 0, 8), 'checked' => $checked, 'usual' => $usual, 'answer' => $this->answer($spikes, $usual)];
    }

    // --------------------------------------------------------------- helpers

    /** One line on what was posted, seen or opened that day, or null when nothing stands out. */
    private function cause(array $spike): ?string
    {
        $parts = [];
        if ($spike['posted']) {
            $parts[] = '“'.$spike['posted'][0].'” was posted '.($spike['posted_same_day'] ? 'that day' : 'the day before');
        }
        if ($spike['top_announcement'] && (! $spike['posted'] || $spike['top_announcement']['title'] !== $spike['posted'][0])) {
            $parts[] = 'most seen: “'.$spike['top_announcement']['title'].'” ('.$spike['top_announcement']['views'].')';
        } elseif ($spike['top_announcement']) {
            $parts[] = $spike['top_announcement']['views'].' students saw it';
        }
        // A document counts only when it was opened often that day, not once or twice.
        if ($spike['top_document'] && $spike['top_document']['opens'] >= max(5, $spike['visits'] / 10)) {
            $parts[] = 'most opened document: “'.$spike['top_document']['name'].'”';
        }
        if ($spike['from_push'] !== null && $spike['from_push'] >= 25) {
            $parts[] = $spike['from_push'].'% of visits came from tapping a notification';
        }

        return $parts ? ucfirst(implode('; ', $parts)).'.' : null;
    }

    /** @return array<string, int> date => devices that opened the page that day */
    private function dailyVisits(Carbon $from, Carbon $to): array
    {
        return EngagementEvent::where('type', 'visit')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, SUM(first_today = 1) as visits')
            ->groupBy('day')
            ->pluck('visits', 'day')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Posts from that day or the day before, and what students saw, opened
     * and came in through on the day itself.
     */
    private function whatHappened(Carbon $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $topSubject = fn (string $type) => EngagementEvent::where('type', $type)->whereNotNull('subject_id')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('subject_id, COUNT(*) as n')->groupBy('subject_id')
            ->orderByDesc('n')->first();

        $announcement = $topSubject('announcement_view');
        $document = $topSubject('document_preview');

        // Of several posts, name the one students saw most that day, else the latest.
        $posts = Announcement::whereBetween('created_at', [$start->copy()->subDay(), $end])
            ->orderByDesc('created_at')->get(['id', 'title', 'created_at'])
            ->sortByDesc(fn ($post) => (int) $post->id === (int) ($announcement?->subject_id ?? 0))->values();

        $loads = EngagementEvent::where('type', 'visit')->whereBetween('created_at', [$start, $end])
            ->selectRaw("COUNT(*) as loads, SUM(source = 'push') as from_push")->first();

        return [
            'posted'           => $posts->pluck('title')->all(),
            'posted_same_day'  => $posts->isNotEmpty() && $posts->first()->created_at->gte($start),
            'top_announcement' => $announcement ? [
                'title' => Announcement::whereKey($announcement->subject_id)->value('title') ?? 'Deleted announcement',
                'views' => (int) $announcement->n,
            ] : null,
            'top_document'     => $document ? [
                'name'  => File::withTrashed()->whereKey($document->subject_id)->value('filename') ?? 'Deleted document',
                'opens' => (int) $document->n,
            ] : null,
            'from_push'        => $loads && $loads->loads > 0 ? (int) round($loads->from_push / $loads->loads * 100) : null,
        ];
    }

    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /**
     * One "unit" of usual variation: 1.4826 x the median absolute deviation.
     * When over half the days are identical that is 0, so the mean absolute
     * deviation (x 1.2533) stands in. Never under 1 visit, so a page with
     * the same few visits every day can't make a small change look huge.
     */
    private function spread(array $values, float $median): float
    {
        $deviations = array_map(fn ($v) => abs($v - $median), $values);
        $mad = $this->median($deviations);
        $scale = $mad > 0 ? 1.4826 * $mad : 1.2533 * (array_sum($deviations) / count($deviations));

        return max(1.0, $scale);
    }

    private function answer(array $spikes, ?int $usual): string
    {
        $b = fn (string $text) => '<strong>'.e($text).'</strong>';

        if ($spikes === []) {
            return 'No unusual days. Every day stayed within its usual range'
                .($usual !== null ? ' (a usual day: about '.$b(number_format($usual).' '.($usual === 1 ? 'visit' : 'visits')).')' : '')
                .'.';
        }

        $top = collect($spikes)->sortByDesc('score')->first();
        $answer = $b(count($spikes).' unusual '.(count($spikes) === 1 ? 'day' : 'days')).'. '
            .(count($spikes) === 1 ? '' : 'Most unusual: ')
            .$b($top['date']->format('D, M j')).' with '.$b(number_format($top['visits']).' visits')
            .($top['times'] ? ', '.$top['times'].'× a usual day ('.$top['usual'].').' : ', on a page that usually gets almost none.');
        return $answer.($top['cause'] ? ' '.e($top['cause']) : '');
    }
}
