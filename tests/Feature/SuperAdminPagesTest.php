<?php

use App\Jobs\RunBackupJob;
use App\Models\ActivityLog;
use App\Models\Backup;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function pagesSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An ordinary officer: an active term in a position that isn't Adviser/President. */
function pagesOfficer(): User
{
    $user = pagesSuperAdmin();

    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);

    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

test('an ordinary officer cannot open the SuperAdmin pages by typing their address', function () {
    $officer = pagesOfficer();

    foreach (['/superAdminDashboard', '/activityLogs', '/backup', '/backup/progress'] as $url) {
        $this->actingAs($officer)->get($url)->assertForbidden();
    }
});

test('an ordinary officer cannot make, download or reschedule backups', function () {
    $officer = pagesOfficer();

    $settings = SiteSetting::current();
    $settings->update(['backup_enabled' => true, 'backup_frequency' => 'daily']);

    $backup = Backup::forceCreate(['name' => 'backup_2026-10-17.zip', 'file_path' => 'backups/backup_2026-10-17.zip']);

    $this->actingAs($officer)->post(route('backup.create'))->assertForbidden();
    $this->actingAs($officer)->post(route('backup.toggle'))->assertForbidden();
    $this->actingAs($officer)->post(route('backup.frequency'), ['frequency' => 'monthly'])->assertForbidden();
    $this->actingAs($officer)->get(route('backup.download', $backup->id))->assertForbidden();

    Bus::assertNotDispatched(RunBackupJob::class);

    $settings->refresh();
    expect($settings->backup_enabled)->toBeTruthy()
        ->and($settings->backup_frequency)->toBe('daily')
        ->and(ActivityLog::count())->toBe(0);
});

test('a SuperAdmin still opens the activity logs and backups', function () {
    $superAdmin = pagesSuperAdmin();

    Backup::forceCreate(['name' => 'backup_2026-10-17.zip', 'file_path' => 'backups/backup_2026-10-17.zip']);

    $this->actingAs($superAdmin)->get('/activityLogs')->assertOk();
    $this->actingAs($superAdmin)->get('/backup')->assertOk()->assertSee('Restore');

    $this->actingAs($superAdmin)->post(route('backup.frequency'), ['frequency' => 'weekly'])->assertRedirect();
    expect(SiteSetting::current()->fresh()->backup_frequency)->toBe('weekly');
});
