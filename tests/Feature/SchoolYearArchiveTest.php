<?php

use App\Models\ActivityLog;
use App\Models\Archive;
use App\Models\File;
use App\Models\Folder;
use App\Models\SchoolYearArchive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Storage::fake('local');
    Storage::fake('cloud');
    Carbon::setTestNow('2026-10-30 10:00:00');
    config(['archive.retention_years' => 3]);
});

afterEach(fn () => Carbon::setTestNow());

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function syAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

function syOfficer(): User
{
    $user = syAdmin();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert(['user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active']);

    return $user;
}

/** A folder stored away under a school year (End Term done), with files that have bytes. */
function storedFolder(string $schoolYear, string $name, array $filenames = ['minutes.pdf'], bool $storedAway = true): Folder
{
    $folder = Folder::create(['name' => $name, 'school_year' => $schoolYear, 'is_archived' => $storedAway]);

    foreach ($filenames as $filename) {
        $localPath = 'uploads/' . $folder->id . '/' . uniqid() . '_' . $filename;
        Storage::disk('local')->put($localPath, 'contents of ' . $filename);
        (new File())->forceFill([
            'folder_id' => $folder->id, 'filename' => $filename, 'filepath' => uniqid() . '_' . $filename,
            'local_path' => $localPath, 'size' => 100, 'storage_type' => 'local',
            'school_year' => $schoolYear, 'is_archived' => $storedAway,
        ])->save();
    }

    return $folder;
}

test('the Archives page lists archived school years, and the Adviser/President sees what is waiting', function () {
    storedFolder('2024-2025', 'Resolutions', ['reso-1.pdf', 'reso-2.pdf']);
    storedFolder('2024-2025', 'Minutes');
    storedFolder('2026-2027', 'This year'); // not finished: never offered

    $this->actingAs(syAdmin())->get(route('archives.index'))
        ->assertOk()
        ->assertSee('archived together, 3 years after the school year ends')
        ->assertSee('Waiting to be archived')
        ->assertSee('SY 2024–2025')
        ->assertSee('2 folders · 3 files')
        ->assertSee('Archived automatically on Aug 1, 2028')
        ->assertSee(route('archives.archiveSchoolYear', '2024-2025'), false)
        ->assertDontSee('SY 2026–2027')
        ->assertSee('No school year is archived yet.');

    $this->actingAs(syOfficer())->get(route('archives.index'))
        ->assertOk()
        ->assertDontSee('Waiting to be archived')
        ->assertDontSee('Archive now');
});

test('"Archive now" archives a whole finished school year, one ZIP per folder', function () {
    $admin = syAdmin();
    storedFolder('2024-2025', 'Resolutions', ['reso-1.pdf', 'reso-2.pdf']);
    storedFolder('2024-2025', 'Minutes');
    $empty = Folder::create(['name' => 'Empty', 'school_year' => '2024-2025', 'is_archived' => true]);

    $this->actingAs($admin)->post(route('archives.archiveSchoolYear', '2024-2025'))
        ->assertRedirect(route('archives.schoolYear', '2024-2025'))
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'SY 2024-2025 archived: 2 folders, 3 files.')
            && str_contains($m, '1 empty folder had nothing to archive'));

    expect(Archive::where('school_year', '2024-2025')->pluck('folder_name')->sort()->values()->all())->toBe(['Minutes', 'Resolutions'])
        ->and(Archive::where('school_year', '2024-2025')->pluck('archive_type')->unique()->all())->toBe(['school_year'])
        ->and(Folder::where('school_year', '2024-2025')->pluck('id')->all())->toBe([$empty->id])
        ->and(SchoolYearArchive::where('school_year', '2024-2025')->first())
            ->status->toBe('archived')->archived_how->toBe('by_hand')->archived_by->toBe($admin->name)
        ->and(ActivityLog::where('activity', 'Archived school year 2024-2025: 2 folder(s), 3 file(s)')->count())->toBe(1);

    $this->actingAs($admin)->get(route('archives.index'))
        ->assertSee('SY 2024–2025')
        ->assertSee('Archived by ' . $admin->name)
        ->assertDontSee('Waiting to be archived');

    $this->actingAs($admin)->get(route('archives.schoolYear', '2024-2025'))
        ->assertOk()
        ->assertSee('Resolutions')
        ->assertSee('2 files')
        ->assertSee('Restore school year');
});

test('a school year that has not ended, or whose End Term was not run, is not archived', function () {
    $admin = syAdmin();
    storedFolder('2026-2027', 'Ended early');
    storedFolder('2025-2026', 'Stored');
    storedFolder('2025-2026', 'Still on the Folders page', storedAway: false);

    $this->actingAs($admin)->post(route('archives.archiveSchoolYear', '2026-2027'))
        ->assertSessionHas('error', 'SY 2026-2027 has not ended yet.');
    $this->actingAs($admin)->post(route('archives.archiveSchoolYear', '2025-2026'))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Run End Term for that school year first'));

    $this->actingAs($admin)->get(route('archives.index'))
        ->assertSee('1 folder still on the Folders page. Run End Term for this school year first.')
        ->assertDontSee(route('archives.archiveSchoolYear', '2025-2026'), false);

    expect(Archive::count())->toBe(0);
});

test('only the Adviser or President archives or restores a school year', function () {
    storedFolder('2024-2025', 'Resolutions');
    $officer = syOfficer();

    $this->actingAs($officer)->post(route('archives.archiveSchoolYear', '2024-2025'))->assertForbidden();
    $this->actingAs($officer)->post(route('archives.restoreSchoolYear', '2024-2025'))->assertForbidden();

    expect(Archive::count())->toBe(0);
});

test('the nightly rule archives a school year 3 years after it ends', function () {
    storedFolder('2022-2023', 'Old minutes');   // ended Jul 31, 2023: due Aug 1, 2026
    storedFolder('2024-2025', 'Recent minutes'); // due Aug 1, 2028

    $this->artisan('archives:school-years', ['--dry-run' => true])
        ->expectsOutputToContain('[DRY RUN] SY 2022-2023')
        ->assertSuccessful();
    expect(Archive::count())->toBe(0);

    $this->artisan('archives:school-years')->assertSuccessful();

    expect(Archive::pluck('school_year')->all())->toBe(['2022-2023'])
        ->and(Archive::first()->archive_type)->toBe('school_year_auto')
        ->and(SchoolYearArchive::where('school_year', '2022-2023')->value('archived_how'))->toBe('automatic')
        ->and(Folder::where('name', 'Recent minutes')->exists())->toBeTrue()
        ->and(ActivityLog::where('user_name', 'System (Retention Policy)')->where('activity', 'like', 'Archived school year 2022-2023%')->count())->toBe(1);
});

test('a restored school year comes back whole, stays until archived again, then can be archived again', function () {
    $admin = syAdmin();
    storedFolder('2022-2023', 'Resolutions', ['reso-1.pdf', 'reso-2.pdf']);
    storedFolder('2022-2023', 'Minutes');
    $this->artisan('archives:school-years')->assertSuccessful();
    expect(Archive::whereNull('restored_at')->count())->toBe(2);

    $this->actingAs($admin)->post(route('archives.restoreSchoolYear', '2022-2023'))
        ->assertRedirect(route('folders.index'))
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'SY 2022-2023 restored: 2 folders, 3 files'));

    expect(Folder::where('school_year', '2022-2023')->where('is_archived', true)->count())->toBe(2)
        ->and(File::where('school_year', '2022-2023')->where('is_archived', true)->count())->toBe(3)
        ->and(SchoolYearArchive::where('school_year', '2022-2023')->value('status'))->toBe('restored');

    // Long past its date, but held: the nightly rule leaves it.
    $this->artisan('archives:school-years')->assertSuccessful();
    expect(Archive::whereNull('restored_at')->count())->toBe(0);

    $this->actingAs($admin)->get(route('archives.index'))
        ->assertSee('Restored. It stays until someone archives it again.');

    $this->actingAs($admin)->post(route('archives.archiveSchoolYear', '2022-2023'))->assertSessionHas('success');
    expect(Archive::whereNull('restored_at')->count())->toBe(2)
        ->and(SchoolYearArchive::where('school_year', '2022-2023')->value('status'))->toBe('archived');
});

test('single folders are no longer archived by hand, and the 90-day reminder is gone', function () {
    $folder = storedFolder('2026-2027', 'Intramurals', storedAway: false);
    $folder->forceFill(['created_at' => now()->subYear(), 'last_accessed_at' => now()->subYear()])->save();
    $admin = syAdmin();

    $this->actingAs($admin)->post('/' . $folder->id . '/archives')->assertNotFound();
    $this->actingAs($admin)->get(route('folders.index'))->assertOk()->assertDontSee('archiveFolderModal');
    $this->actingAs($admin)->get('/superAdminDashboard')->assertOk()->assertDontSee('untouched for');
    // Reports uses MySQL's DATE_FORMAT, so it is checked on MySQL instead (pest-tests-sqlite-gotchas).
});
