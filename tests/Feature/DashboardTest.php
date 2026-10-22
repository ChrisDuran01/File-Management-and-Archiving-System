<?php

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function dashUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'name'                   => 'Ana Reyes',
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An ordinary officer: an active term in a position that isn't SuperAdmin. */
function dashAdmin(): User
{
    $user = dashUser();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert([
        'user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active',
    ]);

    return $user;
}

function dashFile(Folder $folder, string $name): File
{
    $file = new File();
    $file->forceFill([
        'folder_id' => $folder->id, 'filename' => $name, 'filepath' => uniqid() . '.pdf',
        'size' => 2048, 'storage_type' => 'cloud',
    ])->save();

    return $file;
}

beforeEach(function () {
    $open = Folder::create(['name' => 'Minutes']);
    $private = Folder::create(['name' => 'Disciplinary cases', 'is_restricted' => true, 'created_by' => dashUser()->id]);
    dashFile($open, 'minutes-july.pdf');
    dashFile($private, 'complaint-juan.pdf');
    ActivityLog::create(['user_name' => 'Pedro Cruz', 'activity' => 'Failed login attempt', 'ip_address' => '10.0.0.5']);
});

// The cards' own headings and labels - the page's CSS and script mention
// some of these words too, so plain words would match those.
const SUPERADMIN_ONLY = [
    '<span class="kpi-label">Active officers</span>',
    '<h3>Recent activity</h3>',
    '<h3>System health</h3>',
    '<i class="key-line"></i>Logged actions',
    'Add officer</a>',
];

const SHARED_CARDS = [
    '<span class="kpi-label">Storage used</span>',
    '<span class="kpi-label">Files</span>',
    '<span class="kpi-label">Folders</span>',
    '<h3>Activity</h3>',
    '<h3>Needs attention</h3>',
    '<h3>Recent uploads</h3>',
    '<h3>Storage by type</h3>',
    '<h3>Student page</h3>',
];

test('the SuperAdmin dashboard shows everything', function () {
    $this->actingAs(dashUser())->get('/superAdminDashboard')
        ->assertOk()
        ->assertSee([...SHARED_CARDS, ...SUPERADMIN_ONLY], false)
        ->assertSee('Pedro Cruz')
        ->assertSee('minutes-july.pdf')
        ->assertSee('complaint-juan.pdf');
});

test('the Admin dashboard is the same page without what only a SuperAdmin may open', function () {
    $response = $this->actingAs(dashAdmin())->get('/adminDashboard')
        ->assertOk()
        ->assertSee(SHARED_CARDS, false)
        ->assertSee('minutes-july.pdf')
        // nothing from the SuperAdmin pages, and no file from a restricted
        // folder it can't open
        ->assertDontSee('Pedro Cruz')
        ->assertDontSee('complaint-juan.pdf');

    foreach (SUPERADMIN_ONLY as $markup) {
        $response->assertDontSee($markup, false);
    }

    $html = $response->getContent();

    foreach (['/manageAdmins', '/activityLogs', '/backup', '/addAdmin'] as $superAdminPage) {
        expect($html)->not->toContain('href="' . $superAdminPage . '"');
    }
});
