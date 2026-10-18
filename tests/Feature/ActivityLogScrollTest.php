<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function logsSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** $count logs, one a minute, oldest first: "Event 1" ... "Event N". */
function makeLogs(int $count, string $start = '2026-10-01 08:00:00'): void
{
    $at = Carbon::parse($start);
    foreach (range(1, $count) as $n) {
        $log = ActivityLog::create(['user_name' => 'Officer ' . ($n % 3), 'activity' => "Event {$n}", 'ip_address' => '10.0.0.' . ($n % 5)]);
        $log->forceFill(['created_at' => $at->copy()->addMinutes($n)])->save();
    }
}

function nextBatch($test, ?int $before, array $filters = []): array
{
    return $test->getJson(route('activity.logs', $filters + array_filter(['before' => $before])), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->json();
}

test('the page opens with the newest 50 and the counts of everything', function () {
    makeLogs(120);

    $this->actingAs(logsSuperAdmin())->get(route('activity.logs'))
        ->assertOk()
        ->assertSee('Event 120')
        ->assertSee('Event 71')
        ->assertDontSee('Event 70<')
        ->assertSee('120 events')
        ->assertSee('3 users')
        ->assertSee('5 unique IPs');
});

test('scrolling loads older batches with nothing repeated or skipped, even while new logs arrive', function () {
    makeLogs(120);
    $this->actingAs(logsSuperAdmin());

    $first = nextBatch($this, null);
    expect($first['hasMore'])->toBeTrue();

    // Someone does something while the page is being read.
    ActivityLog::create(['user_name' => 'Late', 'activity' => 'Event NEW', 'ip_address' => '10.0.0.9']);

    $seen = [];
    $batch = $first;
    while (true) {
        preg_match_all('/Event (\d+)</', $batch['html'], $m);
        $seen = array_merge($seen, array_map('intval', $m[1]));
        if (! $batch['hasMore']) break;
        $batch = nextBatch($this, $batch['next']);
    }

    expect($seen)->toBe(range(120, 1)); // every one, once, newest first
    expect($batch['html'])->not->toContain('No activity logs found');
});

test('search reaches entries that are not loaded yet', function () {
    makeLogs(120);
    $this->actingAs(logsSuperAdmin());

    $result = nextBatch($this, null, ['q' => 'Event 3']);
    preg_match_all('/Event (\d+)</', $result['html'], $m);

    expect(array_map('intval', $m[1]))->toBe([39, 38, 37, 36, 35, 34, 33, 32, 31, 30, 3])
        ->and($result['stats']['events'])->toBe(11)
        ->and($result['hasMore'])->toBeFalse();
});

test('the date range still filters, including through later batches', function () {
    makeLogs(60, '2026-10-01 23:00:00'); // Events 1-59 on Oct 1, 60 just after midnight
    $this->actingAs(logsSuperAdmin());

    $result = nextBatch($this, null, ['from_date' => '2026-10-02', 'to_date' => '2026-10-02']);
    expect($result['stats']['events'])->toBe(1)
        ->and($result['html'])->toContain('Event 60');

    $oct1 = nextBatch($this, null, ['to_date' => '2026-10-01']);
    expect($oct1['stats']['events'])->toBe(59)->and($oct1['hasMore'])->toBeTrue();
    $rest = nextBatch($this, $oct1['next'], ['to_date' => '2026-10-01']);
    expect(substr_count($rest['html'], 'log-row'))->toBe(9)->and($rest['hasMore'])->toBeFalse();
});

test('a search with no match says so', function () {
    makeLogs(5);

    $this->actingAs(logsSuperAdmin())->get(route('activity.logs', ['q' => 'nothing like this']))
        ->assertOk()
        ->assertSee('No activity logs found.')
        ->assertSee('0 events');
});
