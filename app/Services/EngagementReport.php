<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\EngagementEvent;
use App\Models\File;
use App\Models\PushSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The "Student engagement" tab of Reports & Analytics, built from the
 * anonymous events the public student page sends. Each panel answers one
 * question an SG officer would actually ask, and comes with a one-line
 * answer computed from the numbers - the chart is the evidence, the answer
 * is the point. Aggregation happens in SQL so a full school year of events
 * stays fast.
 *
 * Answers are HTML: dynamic text is escaped with e(), only <strong> is added.
 */
class EngagementReport
{
    private const DELAY_LABELS = ['Within 1 hour', '1–6 hours', '6–24 hours', '1–3 days', '3–7 days', 'After a week'];

    private const HALF_SEEN = ['within the first hour', 'within 6 hours', 'within a day', 'within 3 days', 'within a week', 'only after a week'];

    private const WEEKDAYS = ['Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays', 'Sundays'];

    /** Fewer events than this and a pattern is just noise - say so instead. */
    private const MIN_FOR_PATTERN = 20;

    private Carbon $from;

    private Carbon $to;

    public function build(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo, array $buckets, string $comparedWith): array
    {
        $this->from = $from;
        $this->to = $to;

        $since = EngagementEvent::min('created_at');
        $since = $since ? Carbon::parse($since) : null;
        // A previous period from before recording began would read as a fake 100% jump.
        $comparable = $since !== null && $since->lte($prevFrom);

        $counts = $this->typeCounts($from, $to);
        $prevCounts = $comparable ? $this->typeCounts($prevFrom, $prevTo) : collect();
        $visitTotals = $this->visitTotals($from, $to);
        $prevVisits = $comparable ? $this->visitTotals($prevFrom, $prevTo) : null;

        $kpi = fn (string $label, int $value, ?int $previous) => [
            'label' => $label,
            'value' => $value,
            'previous' => $previous,
            'delta' => $previous ? (int) round(($value - $previous) / $previous * 100) : null,
        ];

        return [
            'since' => $since,
            'comparable' => $comparable,
            'kpis' => [
                $kpi('Visits', $visitTotals['visits'], $prevVisits['visits'] ?? null),
                $kpi('First-time visitors', $visitTotals['fresh'], $prevVisits['fresh'] ?? null),
                $kpi('Announcement views', (int) ($counts['announcement_view'] ?? 0), $comparable ? (int) ($prevCounts['announcement_view'] ?? 0) : null),
                $kpi('Document opens', (int) ($counts['document_preview'] ?? 0), $comparable ? (int) ($prevCounts['document_preview'] ?? 0) : null),
                $kpi('Messages sent', (int) ($counts['message_sent'] ?? 0), $comparable ? (int) ($prevCounts['message_sent'] ?? 0) : null),
            ],
            'visits' => $this->visitsOverTime($buckets, $visitTotals, $prevVisits, $comparedWith, $since),
            'timing' => $this->whenStudentsVisit(),
            'devices' => $this->devices(),
            'notifications' => $this->notifications($counts, $visitTotals),
            'announcements' => $this->topAnnouncements(),
            'reach' => $this->howFastAnnouncementsReach(),
            'documents' => $this->topDocuments(),
            'searches' => $this->searches((int) ($counts['search'] ?? 0)),
        ];
    }

    // ---------------------------------------------------------------- panels

    /** Are students visiting the student page? */
    private function visitsOverTime(array $buckets, array $totals, ?array $prev, string $comparedWith, ?Carbon $since): array
    {
        $visits = array_fill(0, count($buckets['starts']), 0);
        $fresh = $visits;

        $daily = $this->events('visit')
            ->selectRaw('DATE(created_at) as day, SUM(first_today = 1) as visits, SUM(first_ever = 1) as fresh')
            ->groupBy('day')
            ->get();

        foreach ($daily as $row) {
            $i = $this->bucketOf(Carbon::parse($row->day), $buckets['starts']);
            $visits[$i] += (int) $row->visits;
            $fresh[$i] += (int) $row->fresh;
        }

        if ($totals['visits'] === 0) {
            $answer = $since
                ? 'No student visited the page in this period.'
                : 'Nothing recorded yet. Statistics start the first time a student opens the student page.';
        } else {
            $answer = $this->b(number_format($totals['visits'])).' '.($totals['visits'] === 1 ? 'visit' : 'visits');
            if ($totals['fresh'] > 0) {
                $answer .= ', '.$this->b(number_format($totals['fresh'])).' of them from first-time visitors';
            }
            $answer .= '.';

            if ($prev && $prev['visits'] > 0) {
                $pct = (int) round(($totals['visits'] - $prev['visits']) / $prev['visits'] * 100);
                $answer .= $pct === 0
                    ? ' About the same as '.e($comparedWith).'.'
                    : ' That is '.($pct > 0 ? 'up ' : 'down ').$this->b(abs($pct).'%').' from '.e($comparedWith).'.';
            }

            $peak = max($visits);
            if (count($visits) > 1 && $peak > 0) {
                $answer .= ' Busiest '.$buckets['unit'].': '.$this->b($buckets['labels'][array_search($peak, $visits, true)]).' ('.number_format($peak).').';
            }
        }

        return ['labels' => $buckets['labels'], 'visits' => $visits, 'fresh' => $fresh, 'unit' => $buckets['unit'], 'answer' => $answer];
    }

    /** When are students on the page - so when should we post? */
    private function whenStudentsVisit(): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        $cells = $this->events('visit')
            ->selectRaw('WEEKDAY(created_at) as wd, HOUR(created_at) as h, COUNT(*) as n')
            ->groupBy('wd', 'h')
            ->get();

        foreach ($cells as $cell) {
            $grid[(int) $cell->wd][(int) $cell->h] = (int) $cell->n;
        }

        $total = array_sum(array_map('array_sum', $grid));
        $byDay = array_map('array_sum', $grid);
        $byHour = array_map(fn ($h) => array_sum(array_column($grid, $h)), range(0, 23));

        if ($total < self::MIN_FOR_PATTERN) {
            return [
                'grid' => $grid, 'max' => max(1, max(array_map('max', $grid))), 'enough' => false,
                'answer' => 'Not enough visits yet to see a pattern (needs at least '.self::MIN_FOR_PATTERN.', has '.$total.').',
            ];
        }

        // The busiest 3-hour stretch of the day, across all days.
        $bestStart = 0;
        $bestSum = -1;
        for ($h = 0; $h <= 21; $h++) {
            $sum = $byHour[$h] + $byHour[$h + 1] + $byHour[$h + 2];
            if ($sum > $bestSum) {
                [$bestStart, $bestSum] = [$h, $sum];
            }
        }
        $bestDay = array_search(max($byDay), $byDay, true);

        $answer = 'Students visit most on '.$this->b(self::WEEKDAYS[$bestDay]).' and between '
            .$this->b($this->hourLabel($bestStart).' and '.$this->hourLabel($bestStart + 3))
            .' ('.round($bestSum / $total * 100).'% of visits). Post just before then.';

        return ['grid' => $grid, 'max' => max(1, max(array_map('max', $grid))), 'enough' => true, 'answer' => $answer];
    }

    /** Do students use phones or computers? */
    private function devices(): array
    {
        $counts = $this->events('visit')->where('first_today', true)
            ->selectRaw('device, COUNT(*) as n')->groupBy('device')->pluck('n', 'device');
        $total = (int) $counts->sum();

        $rows = collect([
            ['key' => 'mobile', 'label' => 'Phones'],
            ['key' => 'desktop', 'label' => 'Computers'],
            ['key' => 'tablet', 'label' => 'Tablets'],
        ])->map(fn ($d) => $d + [
            'total' => (int) ($counts[$d['key']] ?? 0),
            'percent' => $total ? (int) round(($counts[$d['key']] ?? 0) / $total * 100) : 0,
        ]);

        $phones = $rows->firstWhere('key', 'mobile')['percent'];
        $computers = $rows->firstWhere('key', 'desktop')['percent'];

        $answer = match (true) {
            $total === 0 => 'No visits recorded in this period.',
            $phones >= 50 => $this->b($phones.'%').' of visits are on phones, so keep posts short and photos light.',
            $computers >= 50 => 'Most visits ('.$this->b($computers.'%').') are on computers.',
            default => 'Visits are split between phones ('.$phones.'%) and computers ('.$computers.'%).',
        };

        return ['rows' => $rows->all(), 'total' => $total, 'answer' => $answer];
    }

    /** Do notifications (new announcements and public documents) bring students back? */
    private function notifications($counts, array $visitTotals): array
    {
        $active = PushSubscription::count();
        $on = (int) ($counts['notify_on'] ?? 0);
        $off = (int) ($counts['notify_off'] ?? 0);
        $share = $visitTotals['loads'] > 0 ? (int) round($visitTotals['from_push'] / $visitTotals['loads'] * 100) : null;

        // Turned on / off counts are shown as numbers under the answer.
        $answer = $this->b(number_format($active)).' '.($active === 1 ? 'device gets' : 'devices get').' alerts for new announcements and documents.';

        if ($visitTotals['from_push'] > 0) {
            $answer .= ' '.$this->b($share.'%').' of visits came from tapping one.';
        } elseif ($active > 0 && $visitTotals['loads'] > 0) {
            $answer .= ' No visit came from an alert in this period yet.';
        }

        return ['active' => $active, 'on' => $on, 'off' => $off, 'from_push' => $visitTotals['from_push'], 'share' => $share, 'answer' => $answer];
    }

    /** Which announcements reached the most students - and do photos help? */
    private function topAnnouncements(): array
    {
        $rows = $this->events(['announcement_view', 'announcement_open', 'attachment_download'])
            ->whereNotNull('subject_id')
            ->selectRaw("subject_id, SUM(type = 'announcement_view') as views, SUM(type = 'announcement_open') as opens, SUM(type = 'attachment_download') as downloads")
            ->groupBy('subject_id')
            ->orderByDesc('views')->orderByDesc('opens')
            ->limit(8)
            ->get();

        $titles = Announcement::whereIn('id', $rows->pluck('subject_id'))->pluck('title', 'id');
        $rows = $rows->map(fn ($r) => [
            'title' => $titles[$r->subject_id] ?? 'Deleted announcement',
            'views' => (int) $r->views,
            'opens' => (int) $r->opens,
            'downloads' => (int) $r->downloads,
        ]);

        if ($rows->isEmpty() || $rows->max('views') === 0) {
            return ['rows' => $rows->all(), 'answer' => 'No announcement was seen by a student in this period.'];
        }

        $top = $rows->first();
        $answer = $this->b('“'.$top['title'].'”').' reached the most students: '.$this->b(number_format($top['views']))
            .($top['opens'] ? ', and '.number_format($top['opens']).' opened it in full' : '').'.';

        $ratio = $this->photoEffect();
        if ($ratio !== null) {
            $answer .= match (true) {
                $ratio >= 1.25 => ' Posts with photos reached '.$this->b(round($ratio, 1).'×').' as many students as text-only posts in their first week.',
                $ratio <= 0.8 => ' Text-only posts actually reached more students than posts with photos.',
                default => ' Photos made little difference to how many students a post reached.',
            };
        }

        return ['rows' => $rows->all(), 'answer' => $answer];
    }

    /** How quickly do students see a new announcement? */
    private function howFastAnnouncementsReach(): array
    {
        $byBucket = DB::table('engagement_events as e')
            ->join('announcements as a', 'a.id', '=', 'e.subject_id')
            ->where('e.type', 'announcement_view')
            ->whereBetween('a.created_at', [$this->from, $this->to])
            ->selectRaw('CASE
                WHEN TIMESTAMPDIFF(MINUTE, a.created_at, e.created_at) < 60 THEN 0
                WHEN TIMESTAMPDIFF(MINUTE, a.created_at, e.created_at) < 360 THEN 1
                WHEN TIMESTAMPDIFF(MINUTE, a.created_at, e.created_at) < 1440 THEN 2
                WHEN TIMESTAMPDIFF(MINUTE, a.created_at, e.created_at) < 4320 THEN 3
                WHEN TIMESTAMPDIFF(MINUTE, a.created_at, e.created_at) < 10080 THEN 4
                ELSE 5 END as bucket, COUNT(*) as n')
            ->groupBy('bucket')
            ->pluck('n', 'bucket');

        $counts = array_map(fn ($i) => (int) ($byBucket[$i] ?? 0), array_keys(self::DELAY_LABELS));
        $total = array_sum($counts);
        $percent = array_map(fn ($n) => $total ? round($n / $total * 100, 1) : 0, $counts);

        if ($total < self::MIN_FOR_PATTERN) {
            return [
                'labels' => self::DELAY_LABELS, 'percent' => $percent, 'enough' => false,
                'answer' => 'Not enough views of announcements posted in this period yet (needs at least '.self::MIN_FOR_PATTERN.', has '.$total.').',
            ];
        }

        $cumulative = 0;
        $half = count($counts) - 1;
        foreach ($counts as $i => $n) {
            $cumulative += $n;
            if ($cumulative * 2 >= $total) {
                $half = $i;
                break;
            }
        }
        $withinThreeDays = (int) round(array_sum(array_slice($counts, 0, 4)) / $total * 100);

        // Bucket 3 is "within 3 days" itself - don't say the same window twice.
        $answer = 'Half of all views come '.$this->b(self::HALF_SEEN[$half]).' of posting'
            .($half === 3 ? '' : ', and '.$this->b($withinThreeDays.'%').' within 3 days')
            .'.'
            .($withinThreeDays >= 75 ? ' Post at least 3 days before an event.' : ' Students keep finding posts for days, so reminders help.');

        return ['labels' => self::DELAY_LABELS, 'percent' => $percent, 'enough' => true, 'answer' => $answer];
    }

    /** Which documents do students actually use? */
    private function topDocuments(): array
    {
        $rows = $this->events(['document_preview', 'document_download'])
            ->whereNotNull('subject_id')
            ->selectRaw("subject_id, SUM(type = 'document_preview') as previews, SUM(type = 'document_download') as downloads")
            ->groupBy('subject_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(8)
            ->get();

        $names = File::withTrashed()->whereIn('id', $rows->pluck('subject_id'))->pluck('filename', 'id');
        $rows = $rows->map(fn ($r) => [
            'name' => $names[$r->subject_id] ?? 'Deleted document',
            'previews' => (int) $r->previews,
            'downloads' => (int) $r->downloads,
        ]);

        $public = File::publiclyVisible()->count();
        $opened = $this->events('document_preview')->distinct()->pluck('subject_id');
        $unopened = $public ? File::publiclyVisible()->whereNotIn('id', $opened)->count() : 0;

        if ($rows->isEmpty()) {
            $answer = $public === 0
                ? 'No documents are public yet, so there is nothing for students to open.'
                : 'None of the '.$this->b(number_format($public)).' public documents was opened in this period.';
        } else {
            $top = $rows->first();
            $answer = $this->b('“'.$top['name'].'”').' is the most used document ('.number_format($top['previews']).' '
                .($top['previews'] === 1 ? 'preview' : 'previews').', '.number_format($top['downloads']).' '
                .($top['downloads'] === 1 ? 'download' : 'downloads').').';
            $answer .= $unopened > 0
                ? ' '.$this->b($unopened.' of '.$public).' public documents were never opened, so students may not know they exist.'
                : ' Every public document was opened at least once.';
        }

        return ['rows' => $rows->all(), 'public' => $public, 'unopened' => $unopened, 'answer' => $answer];
    }

    /** What are students searching for - and not finding? */
    private function searches(int $total): array
    {
        $missing = $this->events('search')->where('results', 0)
            ->selectRaw('term, COUNT(*) as n')->groupBy('term')->orderByDesc('n')->limit(6)->get();
        $top = $this->events('search')
            ->selectRaw('term, COUNT(*) as n, MAX(results) as results')->groupBy('term')->orderByDesc('n')->limit(6)->get();
        $zero = $this->events('search')->where('results', 0)->count();

        $answer = match (true) {
            $total === 0 => 'No one searched the documents in this period.',
            $zero === 0 => 'All '.$this->b(number_format($total)).' searches found at least one document.',
            default => $this->b(number_format($zero).' of '.number_format($total)).' searches found nothing. Most wanted: '
                .$this->b('“'.$missing->first()->term.'”').' ('.$missing->first()->n.'×), worth publishing if the SG has it.',
        };

        return ['total' => $total, 'zero' => $zero, 'missing' => $missing->all(), 'top' => $top->all(), 'answer' => $answer];
    }

    // --------------------------------------------------------------- helpers

    /** Average first-week reach of posts with photos ÷ text-only posts, or null if there's too little to compare. */
    private function photoEffect(): ?float
    {
        // Only posts old enough to have had a full week, from this period.
        $latestComparable = Carbon::now()->subDays(7)->min($this->to);
        if ($latestComparable->lt($this->from)) {
            return null;
        }

        $posts = Announcement::withCount('images')
            ->whereBetween('created_at', [$this->from, $latestComparable])
            ->get(['id']);

        $withPhotos = $posts->where('images_count', '>', 0);
        $textOnly = $posts->where('images_count', 0);
        if ($withPhotos->count() < 2 || $textOnly->count() < 2) {
            return null;
        }

        $views = DB::table('engagement_events as e')
            ->join('announcements as a', 'a.id', '=', 'e.subject_id')
            ->where('e.type', 'announcement_view')
            ->whereIn('a.id', $posts->pluck('id'))
            ->whereRaw('e.created_at <= DATE_ADD(a.created_at, INTERVAL 7 DAY)')
            ->groupBy('e.subject_id')
            ->selectRaw('e.subject_id as id, COUNT(*) as n')
            ->pluck('n', 'id');

        $avg = fn ($group) => $group->avg(fn ($post) => (int) ($views[$post->id] ?? 0));
        $textAvg = $avg($textOnly);

        return $textAvg > 0 ? $avg($withPhotos) / $textAvg : null;
    }

    private function events(string|array $types)
    {
        return EngagementEvent::query()
            ->whereIn('type', (array) $types)
            ->whereBetween('created_at', [$this->from, $this->to]);
    }

    private function typeCounts(Carbon $from, Carbon $to)
    {
        return EngagementEvent::whereBetween('created_at', [$from, $to])
            ->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');
    }

    /** visits = devices per day, fresh = first-time devices, loads = every page load. */
    private function visitTotals(Carbon $from, Carbon $to): array
    {
        $row = EngagementEvent::where('type', 'visit')->whereBetween('created_at', [$from, $to])
            ->selectRaw("SUM(first_today = 1) as visits, SUM(first_ever = 1) as fresh, COUNT(*) as loads, SUM(source = 'push') as from_push")
            ->first();

        return [
            'visits' => (int) $row->visits,
            'fresh' => (int) $row->fresh,
            'loads' => (int) $row->loads,
            'from_push' => (int) $row->from_push,
        ];
    }

    private function bucketOf(Carbon $date, array $starts): int
    {
        for ($i = count($starts) - 1; $i > 0; $i--) {
            if ($date->gte($starts[$i])) {
                return $i;
            }
        }

        return 0;
    }

    private function hourLabel(int $hour): string
    {
        return match (true) {
            $hour === 0, $hour === 24 => 'midnight',
            $hour === 12 => 'noon',
            default => ($hour % 12).' '.($hour < 12 ? 'AM' : 'PM'),
        };
    }

    private function b(string $text): string
    {
        return '<strong>'.e($text).'</strong>';
    }
}
