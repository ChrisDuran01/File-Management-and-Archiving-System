<?php

use App\Models\Announcement;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Support\StudentPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // "Make Public" queues student push notifications.
    Bus::fake();
});

/** A signed-in-ready user; no officer_terms row counts as SuperAdmin. */
function cacheSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An ordinary officer, who gets the Admin layout. */
function cacheOfficer(): User
{
    $user = cacheSuperAdmin();

    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);

    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

function cachePublicFile(array $attributes = []): File
{
    $file = new File();
    $file->forceFill(array_merge([
        'folder_id'    => $attributes['folder_id'] ?? Folder::create(['name' => 'General'])->id,
        'filename'     => 'budget-report.pdf',
        'filepath'     => 'budget_' . uniqid() . '.pdf',
        'size'         => 2048,
        'storage_type' => 'cloud',
        'is_public'    => 1,
    ], $attributes))->save();

    return $file->fresh();
}

test('the student page reuses its data instead of reading it again on every visit', function () {
    $file = cachePublicFile();
    $this->get(route('student.dashboard'))->assertOk()->assertSee('budget-report.pdf');

    // A write that skips model events doesn't clear the cache, so it isn't
    // seen yet - proof the second visit came from the cache.
    DB::table('files')->where('id', $file->id)->update(['filename' => 'renamed.pdf']);

    $this->get(route('student.dashboard'))->assertOk()
        ->assertSee('budget-report.pdf')
        ->assertDontSee('renamed.pdf');
});

test('making a file private takes it off the student page straight away', function () {
    $file = cachePublicFile();
    $this->get(route('student.dashboard'))->assertSee('budget-report.pdf');

    $this->actingAs(cacheSuperAdmin())->post(route('file.toggleAccess', $file->id));
    expect((bool) $file->fresh()->is_public)->toBeFalse();

    $this->get(route('student.dashboard'))->assertOk()->assertDontSee('budget-report.pdf');
});

test('restricting its folder takes a file off the student page straight away', function () {
    $folder = Folder::create(['name' => 'Resolutions']);
    cachePublicFile(['folder_id' => $folder->id]);
    $this->get(route('student.dashboard'))->assertSee('budget-report.pdf');

    $folder->update(['is_restricted' => true]);

    $this->get(route('student.dashboard'))->assertOk()->assertDontSee('budget-report.pdf');
});

test('trashing a file, its folder or its document takes it off the student page straight away', function (string $what) {
    $file = cachePublicFile();
    $this->get(route('student.dashboard'))->assertSee('budget-report.pdf');

    match ($what) {
        'file'     => $file->moveToTrash(),
        'folder'   => $file->folder->moveToTrash(),
        'document' => Document::create([
            'file_id' => $file->id, 'folder_id' => $file->folder_id,
            'title' => 'Budget report', 'date_received' => now(),
        ])->moveToTrash(),
    };

    $this->get(route('student.dashboard'))->assertOk()->assertDontSee('budget-report.pdf');
})->with(['file', 'folder', 'document']);

test('restoring a folder from Trash brings its files back to the student page', function () {
    $file = cachePublicFile();
    $folder = $file->folder;
    $folder->moveToTrash();
    $this->get(route('student.dashboard'))->assertDontSee('budget-report.pdf');

    Folder::withTrashed()->find($folder->id)->restoreFromTrash();

    $this->get(route('student.dashboard'))->assertOk()->assertSee('budget-report.pdf');
});

test('a new announcement shows on the student page straight away', function () {
    $this->get(route('student.dashboard'))->assertOk();

    Announcement::create(['title' => 'Intramurals schedule', 'body' => '<p>See you there.</p>']);

    $this->get(route('student.dashboard'))->assertOk()->assertSee('Intramurals schedule');
});

test('a copy built while something changed is not kept as the current one', function () {
    $builds = 0;
    $build = function () use (&$builds) {
        $builds++;
        // An officer hides a file while this visit is still reading.
        if ($builds === 1) {
            StudentPageCache::forget();
        }

        return ['build' => $builds];
    };

    expect(StudentPageCache::remember($build))->toBe(['build' => 1])
        ->and(StudentPageCache::remember($build))->toBe(['build' => 2])
        ->and(StudentPageCache::remember($build))->toBe(['build' => 2]);
});

test('a copy saved by older code is rebuilt instead of used', function () {
    StudentPageCache::remember(fn () => ['shape' => 'old']);

    // As if saved before a deploy that changed what the page reads.
    Cache::put('student_page:data', ['code' => 'an older deploy'] + Cache::get('student_page:data'), 600);

    expect(StudentPageCache::remember(fn () => ['shape' => 'new']))->toBe(['shape' => 'new']);
});

test('both officer layouts show the notification bell once', function () {
    $officer = $this->actingAs(cacheOfficer())->get(route('folders.index'))->assertOk();
    expect(substr_count($officer->getContent(), 'id="notifBtn"'))->toBe(1);

    $superAdmin = $this->actingAs(cacheSuperAdmin())->get('/activityLogs')->assertOk();
    expect(substr_count($superAdmin->getContent(), 'id="notifBtn"'))->toBe(1);
});
