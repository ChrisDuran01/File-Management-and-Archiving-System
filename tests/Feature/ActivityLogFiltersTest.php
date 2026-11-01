<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function filtersSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

function logAt(string $when, string $who, string $what): void
{
    $log = ActivityLog::create(['user_name' => $who, 'activity' => $what, 'ip_address' => '10.0.0.1']);
    $log->forceFill(['created_at' => Carbon::parse($when)])->save();
}

/** One batch as the page's script asks for it: the entries' texts, newest first, and the counts. */
function filteredLogs($test, array $filters): array
{
    $json = $test->getJson(route('activity.logs', $filters), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->json();

    preg_match_all('/class="al-detail">([^<]*)</', $json['html'], $m);

    return $json + ['texts' => array_map(fn ($text) => html_entity_decode($text, ENT_QUOTES), $m[1])];
}

test('the officer filter shows one person only', function () {
    logAt('2026-10-20 08:00', 'Juan Cruz', 'Logged in');
    logAt('2026-10-20 09:00', 'Ana Reyes', 'Logged in');
    logAt('2026-10-20 10:00', 'Juan Cruz', 'Uploaded 1 file(s): minutes.pdf');
    $this->actingAs(filtersSuperAdmin());

    $result = filteredLogs($this, ['user' => 'Juan Cruz']);

    expect($result['texts'])->toBe(['Uploaded 1 file(s): minutes.pdf', 'Logged in'])
        ->and($result['stats']['users'])->toBe(1);
});

test('the type filter sorts entries by how their text starts', function () {
    logAt('2026-10-20 08:00', 'Juan', 'Logged in');
    logAt('2026-10-20 08:01', 'juan@example.com', 'Failed login attempt');
    logAt('2026-10-20 08:02', 'Juan', 'Uploaded 1 file(s): announcement.pdf');
    logAt('2026-10-20 08:03', 'Juan', 'Posted announcement: Foundation Day');
    logAt('2026-10-20 08:04', 'Juan', 'Created backup: sg.zip (1 MB)');
    logAt('2026-10-20 08:05', 'Juan', 'Something new nobody sorted');
    $this->actingAs(filtersSuperAdmin());

    expect(filteredLogs($this, ['type' => 'files'])['texts'])->toBe(['Uploaded 1 file(s): announcement.pdf'])
        ->and(filteredLogs($this, ['type' => 'student-page'])['texts'])->toBe(['Posted announcement: Foundation Day'])
        ->and(filteredLogs($this, ['type' => 'security'])['texts'])->toBe(['Failed login attempt'])
        ->and(filteredLogs($this, ['type' => 'backups'])['texts'])->toBe(['Created backup: sg.zip (1 MB)'])
        ->and(filteredLogs($this, ['type' => 'other'])['texts'])->toBe(['Something new nobody sorted']);

    $this->getJson(route('activity.logs', ['type' => 'nonsense']), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertUnprocessable();
});

test('filters combine and stay on while older batches load', function () {
    foreach (range(1, 130) as $n) {
        logAt("2026-10-01 08:00 +{$n} minutes", $n % 2 ? 'Juan' : 'Ana', $n % 7 ? "Downloaded file: f{$n}.pdf" : 'Logged in');
    }
    $this->actingAs(filtersSuperAdmin());
    $filters = ['user' => 'Juan', 'type' => 'files'];

    // Odd numbers up to 130 that aren't multiples of 7: 65 - 9.
    $first = filteredLogs($this, $filters);
    expect($first['stats']['events'])->toBe(56)->and($first['hasMore'])->toBeTrue();

    $rest = filteredLogs($this, $filters + ['before' => $first['next']]);
    expect($rest['texts'])->toHaveCount(6)
        ->and($rest['hasMore'])->toBeFalse()
        ->and($rest['html'])->not->toContain('Logged in')->not->toContain('<b>Ana</b>');
});

test('quick periods, a school year and open-ended custom dates', function () {
    Carbon::setTestNow('2026-10-30 15:00:00');
    // Oldest first, as real logs are written: the list is newest-saved first.
    logAt('2026-06-01 09:00', 'A', 'Last school year');
    logAt('2026-09-15 09:00', 'A', 'September');
    logAt('2026-10-23 09:00', 'A', 'A week ago');
    logAt('2026-10-24 09:00', 'A', 'Six days ago');
    logAt('2026-10-30 09:00', 'A', 'Today');
    $this->actingAs(filtersSuperAdmin());
    $texts = fn (array $filters) => filteredLogs($this, $filters)['texts'];

    expect($texts(['range' => 'today']))->toBe(['Today'])
        ->and($texts(['range' => '7d']))->toBe(['Today', 'Six days ago'])
        ->and($texts(['range' => 'month']))->toBe(['Today', 'Six days ago', 'A week ago'])
        ->and($texts(['sy' => '2026-2027']))->toBe(['Today', 'Six days ago', 'A week ago', 'September'])
        ->and($texts(['sy' => '2025-2026']))->toBe(['Last school year'])
        ->and($texts(['from_date' => '2026-10-01']))->toBe(['Today', 'Six days ago', 'A week ago'])
        ->and($texts(['to_date' => '2026-09-30']))->toBe(['September', 'Last school year'])
        ->and($texts([]))->toHaveCount(5);
});

test('the page lists officers apart from typed emails, every type, and period links that keep the other filters', function () {
    Carbon::setTestNow('2026-10-30 15:00:00');
    logAt('2026-10-20 08:00', 'Juan Cruz', 'Logged in');
    logAt('2026-10-20 08:01', 'nobody@example.com', 'Failed login attempt');
    $filters = ['user' => 'Juan Cruz', 'type' => 'sign-ins'];

    $this->actingAs(filtersSuperAdmin())->get(route('activity.logs', $filters))
        ->assertOk()
        ->assertSeeInOrder(['<optgroup label="Officers">', 'Juan Cruz', '<optgroup label="Emails typed at sign-in">', 'nobody@example.com'], false)
        ->assertSeeInOrder(['All types', 'Sign-ins', 'Security', 'Files', 'Folders', 'Documents', 'Student page', 'Officers', 'Backups &amp; archives', 'Other'], false)
        ->assertSee(e(route('activity.logs', ['range' => 'today'] + $filters)), false)
        ->assertSee(e(route('activity.logs', ['sy' => '2026-2027'] + $filters)), false)
        ->assertSee('name="from_date"', false)
        ->assertSee('All dates')
        ->assertSee('Clear filters');

    $this->get(route('activity.logs', ['range' => '7d']))
        ->assertSee('Oct 24, 2026 – Oct 30, 2026');
});
