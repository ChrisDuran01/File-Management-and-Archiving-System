<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\VisitSpikes;
use App\Support\StudentPageCache;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills the Student engagement tab of Reports with ten weeks of made-up
 * student-page activity, so its charts and the "Were there unusual days?"
 * panel can be shown before real students use the page: busy weekdays,
 * quiet weekends, three days that spike after a sample announcement, and
 * one busy day that is not unusual enough to count.
 *
 * Everything it adds is listed in storage/app/engagement-demo.json and is
 * deleted again with --remove. The five sample announcements are written
 * straight to the table, so no push notification goes out for them, but
 * they do show on the student page until removed.
 */
class EngagementDemoData extends Command
{
    protected $signature = 'engagement:demo-data
        {--remove : Delete the sample data this command added}
        {--force : Allow it on a production server}';

    protected $description = 'Add (or --remove) ten weeks of sample student-page activity for the Reports Student engagement tab';

    private const MANIFEST = 'app/engagement-demo.json';

    private const DAYS = 70;

    /** Relative weight of each hour of the day: a peak at noon and in the evening. */
    private const HOUR_WEIGHTS = [1, 0, 0, 0, 0, 1, 3, 6, 7, 6, 6, 8, 10, 8, 6, 6, 7, 8, 9, 11, 12, 10, 6, 3];

    private const SEARCHES = [
        ['resolution', 1], ['qsu', 1], ['reso 21', 1], ['clearance', 0], ['intrams schedule', 0],
        ['id validation', 0], ['cbl', 0], ['calendar of activities', 0],
    ];

    private array $rows = [];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('This is a production server. Sample data would mix with real student visits; add --force if you really mean it.');

            return self::FAILURE;
        }

        return $this->option('remove') ? $this->remove() : $this->add();
    }

    private function add(): int
    {
        if (is_file(storage_path(self::MANIFEST))) {
            $this->error('Sample data is already in. Run "php artisan engagement:demo-data --remove" first.');

            return self::FAILURE;
        }

        mt_srand(2026);
        $end = Carbon::yesterday();
        $start = $end->copy()->subDays(self::DAYS - 1);
        $at = fn (int $daysBeforeEnd, string $time) => Carbon::parse($end->copy()->subDays($daysBeforeEnd)->toDateString().' '.$time);

        // Three posts that bring a crowd, and two ordinary ones.
        $posts = [
            ['title' => "SG Committee Hunt: applications are open", 'at' => $at(62, '12:00'), 'pull' => null,
                'body' => 'Join an SG committee for this school year. Apply at the SG office until Friday.'],
            ['title' => "ICB 26: Leaders' Academy & Convergence – reminders for delegates", 'at' => $at(44, '07:30'), 'pull' => ['visits' => 64, 'push' => .40],
                'body' => 'Delegates assemble at the University Gymnasium at 7:00 AM. Bring your ID and a pen.'],
            ['title' => 'Student Council membership dues: collection starts this week', 'at' => $at(40, '18:00'), 'pull' => ['visits' => 85, 'push' => .30, 'next_day' => true],
                'body' => 'Collection of Student Council membership dues for the first semester starts tomorrow, as approved in an SG Executive Resolution.'],
            ['title' => 'Lost IDs can be claimed at the SG office', 'at' => $at(25, '16:00'), 'pull' => null,
                'body' => 'IDs found on campus are kept at the SG office. Claim yours on weekdays from 8 AM to 5 PM.'],
            ['title' => 'Sportsfest 2026: schedule of games and venues', 'at' => $at(17, '12:10'), 'pull' => ['visits' => 110, 'push' => .25],
                'body' => 'The full schedule of games and venues for the Simultaneous Sportsfest Celebration.'],
        ];

        $documents = File::publiclyVisible()->pluck('id')->all();
        $bumpDay = $end->copy()->subDays(30)->toDateString();   // busy, but not unusual
        $firstId = (int) DB::table('engagement_events')->max('id') + 1;

        DB::transaction(function () use (&$posts, $start, $end, $documents, $bumpDay) {
            foreach ($posts as &$post) {
                $post['id'] = DB::table('announcements')->insertGetId([
                    'title' => $post['title'],
                    'body' => $post['body'].' (Sample announcement for the Reports demo.)',
                    'hide_from_search' => true,
                    'created_at' => $post['at'], 'updated_at' => $post['at'],
                ]);
            }
            unset($post);

            $spikeDays = [];
            foreach ($posts as $post) {
                if ($post['pull']) {
                    $day = $post['at']->copy()->addDays(! empty($post['pull']['next_day']) ? 1 : 0)->toDateString();
                    $spikeDays[$day] = $post;
                }
            }

            for ($i = 0, $day = $start->copy(); $day->lte($end); $i++, $day->addDay()) {
                $date = $day->toDateString();
                $base = match (true) {
                    $day->isSunday() => mt_rand(4, 8),
                    $day->isSaturday() => mt_rand(7, 12),
                    default => mt_rand(17, 25),
                } + intdiv($i, 12);                               // slowly more students find the page
                $spike = $spikeDays[$date] ?? null;
                $afterSpike = isset($spikeDays[$day->copy()->subDay()->toDateString()]);

                $visits = match (true) {
                    $spike !== null => $spike['pull']['visits'],
                    $date === $bumpDay => 34,
                    $afterSpike => (int) round($base * 1.3),
                    default => $base,
                };
                $pushShare = $spike['pull']['push'] ?? .06;
                $newShare = $spike ? .25 : .35 - .27 * $i / self::DAYS;
                // A spike's crowd arrives in the hours after the post goes up.
                $from = $spike && $spike['at']->isSameDay($day) ? $spike['at']->hour : null;

                for ($v = 0; $v < $visits; $v++) {
                    $time = $this->time($day, $from);
                    $device = $this->device();
                    $this->event('visit', $time, ['device' => $device, 'first_today' => 1,
                        'first_ever' => (int) ($this->chance($newShare)), 'source' => $this->chance($pushShare) ? 'push' : null]);
                    for ($r = mt_rand(0, 2); $r > 0; $r--) {
                        $this->event('visit', $time->copy()->addMinutes(mt_rand(1, 40)), ['device' => $device, 'first_today' => 0, 'first_ever' => 0]);
                    }
                }

                // Each post is seen most on its first day, then less and less for a week.
                foreach ($posts as $post) {
                    $age = (int) $post['at']->copy()->startOfDay()->diffInDays($day->copy()->startOfDay(), false);
                    if ($age < 0 || $age > 7) {
                        continue;
                    }
                    $share = ($post['pull'] ? [.55, .45, .2, .1, .06, .04, .03, .02] : [.35, .15, .08, .05, .03, .02, .01, .01])[$age];
                    if ($post['pull'] && ! empty($post['pull']['next_day'])) {
                        $share = [.3, .7, .2, .1, .06, .04, .03, .02][$age];
                    }
                    $views = (int) round($visits * $share);
                    for ($n = 0; $n < $views; $n++) {
                        $time = $this->time($day, $age === 0 ? $post['at']->hour : null);
                        if ($time->lt($post['at'])) {
                            $time = $post['at']->copy()->addMinutes(mt_rand(5, 120));
                        }
                        $this->event('announcement_view', $time, ['subject_id' => $post['id']]);
                        if ($this->chance(.35)) {
                            $this->event('announcement_open', $time->copy()->addMinutes(1), ['subject_id' => $post['id']]);
                        }
                    }
                }

                // Public documents, searches, notifications, messages.
                $duesDay = $spike && ! empty($spike['pull']['next_day']);
                foreach ($documents as $k => $docId) {
                    $opens = mt_rand(0, 2) + ($duesDay && $k === 0 ? 18 : 0);
                    for ($n = 0; $n < $opens; $n++) {
                        $time = $this->time($day);
                        $this->event('document_preview', $time, ['subject_id' => $docId]);
                        if ($this->chance(.3)) {
                            $this->event('document_download', $time->copy()->addMinutes(1), ['subject_id' => $docId]);
                        }
                    }
                }
                for ($n = mt_rand(0, 1); $n > 0; $n--) {
                    [$term, $results] = self::SEARCHES[mt_rand(0, count(self::SEARCHES) - 1)];
                    $this->event('search', $this->time($day), ['term' => $term, 'results' => $results]);
                }
                if ($duesDay) {
                    foreach (['membership fee' => 14, 'dues' => 6] as $term => $times) {
                        for ($n = 0; $n < $times; $n++) {
                            $this->event('search', $this->time($day), ['term' => $term, 'results' => 0]);
                        }
                    }
                }
                for ($n = mt_rand(0, 1) + ($spike ? 8 : 0); $n > 0; $n--) {
                    $this->event('notify_on', $this->time($day, $from));
                }
                if ($this->chance(.06)) {
                    $this->event('notify_off', $this->time($day));
                }
                if ($this->chance(.1)) {
                    $this->event('message_sent', $this->time($day));
                }
            }

            $this->flush(true);
        });

        $lastId = (int) DB::table('engagement_events')->max('id');
        $added = DB::table('engagement_events')->whereBetween('id', [$firstId, $lastId])->count();
        file_put_contents(storage_path(self::MANIFEST), json_encode([
            'added_at' => now()->toDateTimeString(),
            'events' => [$firstId, $lastId],
            'announcements' => array_column($posts, 'id'),
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ], JSON_PRETTY_PRINT));
        StudentPageCache::forget();

        $this->info('Added '.number_format($added).' sample events from '.$start->format('M j').' to '.$end->format('M j, Y')
            .', and '.count($posts).' sample announcements (no notifications were sent).');

        $since = Carbon::parse(DB::table('engagement_events')->min('created_at'));
        $found = app(VisitSpikes::class)->find($start, $end->copy()->endOfDay(), $since);
        $this->table(['Unusual day', 'Visits', 'Usual day', 'Score'], array_map(fn ($s) => [
            $s['date']->format('D, M j'), $s['visits'], $s['usual'], $s['score'],
        ], $found['spikes']));
        $this->line('Not unusual enough to count: '.Carbon::parse($bumpDay)->format('D, M j').' with 34 visits.');
        $this->line('See it in Reports > Student engagement. Remove it with: php artisan engagement:demo-data --remove');

        return self::SUCCESS;
    }

    private function remove(): int
    {
        $path = storage_path(self::MANIFEST);
        if (! is_file($path)) {
            $this->info('No sample data to remove.');

            return self::SUCCESS;
        }

        $manifest = json_decode(file_get_contents($path), true);
        [$first, $last] = $manifest['events'];

        $events = DB::table('engagement_events')->whereBetween('id', [$first, $last])->delete();
        $posts = DB::table('announcements')->whereIn('id', $manifest['announcements'])->delete();
        unlink($path);
        StudentPageCache::forget();

        $this->info('Removed '.number_format($events).' sample events and '.$posts.' sample announcements.');

        return self::SUCCESS;
    }

    // --------------------------------------------------------------- helpers

    private function event(string $type, Carbon $time, array $attributes = []): void
    {
        $this->rows[] = array_merge([
            'type' => $type, 'subject_id' => null, 'device' => null, 'source' => null, 'first_today' => null,
            'first_ever' => null, 'term' => null, 'results' => null, 'created_at' => $time->toDateTimeString(),
        ], $attributes);
        $this->flush();
    }

    private function flush(bool $all = false): void
    {
        if ($this->rows && ($all || count($this->rows) >= 500)) {
            DB::table('engagement_events')->insert($this->rows);
            $this->rows = [];
        }
    }

    /** A time on $day, by the usual hours of the page, or within 8 hours of $fromHour. */
    private function time(Carbon $day, ?int $fromHour = null): Carbon
    {
        $hour = $fromHour !== null
            ? min(23, $fromHour + [0, 0, 1, 1, 2, 3, 4, 6, 8][mt_rand(0, 8)])
            : $this->weightedHour();

        return $day->copy()->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));
    }

    private function weightedHour(): int
    {
        $pick = mt_rand(1, array_sum(self::HOUR_WEIGHTS));
        foreach (self::HOUR_WEIGHTS as $hour => $weight) {
            $pick -= $weight;
            if ($pick <= 0) {
                return $hour;
            }
        }

        return 12;
    }

    private function device(): string
    {
        $r = mt_rand(1, 100);

        return $r <= 74 ? 'mobile' : ($r <= 95 ? 'desktop' : 'tablet');
    }

    private function chance(float $p): bool
    {
        return mt_rand() / mt_getrandmax() < $p;
    }
}
