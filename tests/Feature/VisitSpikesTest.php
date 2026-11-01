<?php

use App\Models\Announcement;
use App\Services\VisitSpikes;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-30 12:00:00');
});

/** $n devices opening the student page on $day, plus reloads that must not count. */
function spikeVisits(string $day, int $n, int $fromPush = 0, int $reloads = 0): void
{
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $rows[] = ['type' => 'visit', 'device' => 'mobile', 'first_today' => true, 'first_ever' => false,
            'source' => $i < $fromPush ? 'push' : null, 'created_at' => $day.' 10:00:00'];
    }
    for ($i = 0; $i < $reloads; $i++) {
        $rows[] = ['type' => 'visit', 'device' => 'mobile', 'first_today' => false, 'first_ever' => false,
            'source' => null, 'created_at' => $day.' 11:00:00'];
    }
    foreach (array_chunk($rows, 200) as $chunk) {
        DB::table('engagement_events')->insert($chunk);
    }
}

/** Every day from $first to $last gets visits from $perDay($date). */
function spikeDays(string $first, string $last, callable $perDay): void
{
    for ($d = Carbon::parse($first); $d->lte(Carbon::parse($last)); $d->addDay()) {
        spikeVisits($d->toDateString(), $perDay($d));
    }
}

function spikesFind(string $from = '2026-10-01', string $to = '2026-11-30', ?string $since = '2026-10-01'): array
{
    return app(VisitSpikes::class)->find(Carbon::parse($from), Carbon::parse($to)->endOfDay(), $since ? Carbon::parse($since) : null);
}

test('a day far above the four weeks before it is reported, with what was posted and seen that day', function () {
    spikeDays('2026-10-01', '2026-11-30', fn (Carbon $d) => 4 + $d->day % 3);
    spikeVisits('2026-10-10', 0, reloads: 50);           // one student reloading all day is not a spike
    DB::table('engagement_events')->where('created_at', 'like', '2026-10-29%')->delete();
    spikeVisits('2026-10-29', 40, fromPush: 12);

    $post = new Announcement();
    $post->forceFill(['title' => 'Intramurals schedule', 'body' => 'Games start Monday.', 'created_at' => '2026-10-29 08:00:00'])->save();
    DB::table('engagement_events')->insert(array_fill(0, 30, [
        'type' => 'announcement_view', 'subject_id' => $post->id, 'created_at' => '2026-10-29 09:00:00',
    ]));

    $result = spikesFind();

    expect($result['spikes'])->toHaveCount(1);
    $spike = $result['spikes'][0];
    expect($spike['date']->toDateString())->toBe('2026-10-29')
        ->and($spike['visits'])->toBe(40)
        ->and($spike['usual'])->toBe(5)
        ->and($spike['times'])->toBe(8.0)
        ->and($spike['score'])->toBeGreaterThanOrEqual(VisitSpikes::THRESHOLD)
        ->and($spike['cause'])->toContain('“Intramurals schedule” was posted that day')
        ->and($spike['cause'])->toContain('30 students saw it')
        ->and($spike['cause'])->toContain('30% of visits came from tapping a notification')
        ->and($result['answer'])->toContain('1 unusual day')
        ->and($result['answer'])->toContain('Thu, Oct 29');
});

test('a small jump on a quiet page is not reported', function () {
    spikeDays('2026-10-01', '2026-11-30', fn (Carbon $d) => $d->day % 2);
    DB::table('engagement_events')->where('created_at', 'like', '2026-11-12%')->delete();
    spikeVisits('2026-11-12', 7);

    $result = spikesFind();

    expect($result['spikes'])->toBe([])
        ->and($result['answer'])->toContain('No unusual days');
});

test('a normal busy weekday is not a spike, a real one is', function () {
    // Weekdays busy, weekends quiet - the pattern of a school.
    spikeDays('2026-10-01', '2026-11-29', fn (Carbon $d) => $d->isWeekend() ? 2 : 20);
    spikeVisits('2026-11-30', 26);                       // a Monday a little above usual

    expect(spikesFind(from: '2026-11-30')['spikes'])->toBe([]);

    DB::table('engagement_events')->where('created_at', 'like', '2026-11-30%')->delete();
    spikeVisits('2026-11-30', 60);

    $spikes = spikesFind(from: '2026-11-30')['spikes'];
    expect($spikes)->toHaveCount(1)
        ->and($spikes[0]['usual'])->toBe(20)
        ->and($spikes[0]['times'])->toBe(3.0);
});

test('an earlier spike does not hide a later one', function () {
    spikeDays('2026-10-01', '2026-11-30', fn (Carbon $d) => 4 + $d->day % 3);
    DB::table('engagement_events')->where('created_at', 'like', '2026-11-02%')->delete();
    DB::table('engagement_events')->where('created_at', 'like', '2026-11-20%')->delete();
    spikeVisits('2026-11-02', 80);
    spikeVisits('2026-11-20', 30);                       // the Nov 2 spike is inside this day's 28-day window

    $result = spikesFind();

    expect(array_map(fn ($s) => $s['date']->toDateString(), $result['spikes']))->toBe(['2026-11-20', '2026-11-02'])
        ->and($result['answer'])->toContain('2 unusual days')
        ->and($result['answer'])->toContain('Mon, Nov 2');
});

test('a day needs two weeks of recorded visits before it can be judged', function () {
    spikeDays('2026-11-20', '2026-11-30', fn () => 5);

    expect(spikesFind(from: '2026-11-01', since: '2026-11-20')['answer'])->toContain('Needs at least 2 weeks')
        ->and(spikesFind(since: null)['answer'])->toContain('Nothing recorded yet');
});
