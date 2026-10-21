<?php

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Notifications\FileUploadedToYourFolder;
use App\Services\CloudFileUploader;
use App\Services\FolderArchiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const PHONE_AGENT   = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36';
const DESKTOP_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

beforeEach(function () {
    Bus::fake();
    Storage::fake('public');
    Storage::fake('local');
    Storage::fake('cloud');

    // Every request in this file comes from a phone unless a test says otherwise.
    $this->withHeader('User-Agent', PHONE_AGENT);
});

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function phoneAppSuperAdmin(): User
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
function phoneAppOfficer(): User
{
    $user = phoneAppSuperAdmin();

    $positionId = DB::table('positions')->insertGetId([
        'position_name'  => 'Secretary',
        'is_super_admin' => false,
    ]);

    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

function phoneAppFile(Folder $folder, string $filename, array $attributes = []): File
{
    $localPath = 'uploads/' . $folder->id . '/' . uniqid() . '_' . $filename;
    Storage::disk('local')->put($localPath, 'contents of ' . $filename);

    $file = new File();
    $file->forceFill(array_merge([
        'folder_id'    => $folder->id,
        'filename'     => $filename,
        'filepath'     => uniqid() . '_' . $filename,
        'local_path'   => $localPath,
        'size'         => 2048,
        'storage_type' => 'both',
    ], $attributes))->save();

    return $file->fresh();
}

test('the app is offered on phones only', function () {
    $this->get(route('app.login'))
        ->assertOk()
        ->assertSee('<h3>Sign in</h3>', false);

    $this->withHeader('User-Agent', DESKTOP_AGENT)->get(route('app.login'))
        ->assertOk()
        ->assertSee('This app is for phones')
        ->assertDontSee('<h3>Sign in</h3>', false);

    $this->withHeader('User-Agent', DESKTOP_AGENT)->actingAs(phoneAppOfficer())
        ->postJson(route('app.sync'), ['have' => []])
        ->assertForbidden();
});

test('there is one sign-in page: a phone goes on to the app, a computer to the website', function () {
    $user = phoneAppOfficer();

    // Inside the app it stays at the app's address, but it is the website's page.
    $this->get(route('app.home'))->assertRedirect(route('app.login'));
    $this->get(route('app.login'))->assertSee('name="from_app"', false);
    $this->get(route('login'))->assertDontSee('name="from_app"', false)->assertDontSee('Open the officers app');

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'from_app' => 1])
        ->assertRedirect(route('app.home'));
    $this->post(route('logout'), ['from_app' => 1])->assertRedirect(route('app.login'));

    // Signing in on the website from a phone also lands in the app.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('app.home'));
    $this->post(route('logout'))->assertRedirect('/login');

    // A computer goes to the website, as before.
    $this->withHeader('User-Agent', DESKTOP_AGENT)
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/adminDashboard');
});

test('an officer can browse folders and open a file on the phone', function () {
    $officer = phoneAppOfficer();
    $owner   = phoneAppOfficer();

    $folder = Folder::create(['name' => 'Resolutions', 'school_year' => '2026-2027']);
    $locked = Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => $owner->id]);
    $stored = Folder::create(['name' => 'Old Minutes', 'school_year' => '2024-2025', 'is_archived' => true]);

    $file = phoneAppFile($folder, 'Resolution No. 12.pdf', ['is_pending_approval' => true]);
    $file->touchAccessed();
    phoneAppFile($stored, 'minutes-2024.pdf', ['is_archived' => true, 'school_year' => '2024-2025']);

    $this->actingAs($officer)->get(route('app.home'))
        ->assertOk()
        ->assertSee('Recently opened')
        ->assertSee('Resolution No. 12.pdf')
        ->assertSee('Resolutions')
        ->assertSee('Restricted · No access')
        ->assertDontSee('Old Minutes');

    $this->actingAs($officer)->get(route('app.folders'))
        ->assertOk()
        ->assertSee('Previous school years')
        ->assertSee('School year 2024-2025');

    $this->actingAs($officer)->get(route('app.year', '2024-2025'))
        ->assertOk()
        ->assertSee('Old Minutes');

    $this->actingAs($officer)->get(route('app.folder', $folder->id))
        ->assertOk()
        ->assertSee('Resolution No. 12.pdf')
        ->assertSee('Subject for approval')
        ->assertSee('Add file');

    // A folder stored under a past school year is read-only here too.
    $this->actingAs($officer)->get(route('app.folder', $stored->id))
        ->assertOk()
        ->assertSee('minutes-2024.pdf')
        ->assertDontSee('Add file');

    $this->actingAs($officer)->get(route('app.folder', $locked->id))
        ->assertRedirect(route('app.folders'));

    $this->actingAs($officer)->get(route('app.file', $file->id))
        ->assertOk()
        ->assertSee('Keep offline')
        ->assertSee('Download')
        ->assertSee(route('files.download', $file->id), false);
});

test('a file\'s bytes go only to someone who can open it', function () {
    $owner = phoneAppOfficer();
    $other = phoneAppOfficer();

    $locked = Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => $owner->id]);
    $file   = phoneAppFile($locked, 'ledger.pdf');

    $this->get(route('app.file.raw', $file->id))->assertRedirect(route('app.login'));
    $this->actingAs($other)->get(route('app.file.raw', $file->id))->assertForbidden();
    $this->actingAs($owner)->get(route('app.file.raw', $file->id))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // No local copy on the server: it comes from cloud storage instead.
    $cloudOnly = phoneAppFile(Folder::create(['name' => 'Letters']), 'letter.pdf', ['local_path' => null, 'storage_type' => 'cloud']);
    Storage::disk('cloud')->put($cloudOnly->filepath, 'from the cloud');

    $this->actingAs($other)->get(route('app.file.raw', $cloudOnly->id))
        ->assertOk()
        ->assertSee('from the cloud');
});

test('automatic saving covers this school year\'s open files, within the size limits', function () {
    config(['officer_app.offline.auto_max_file_mb' => 1, 'officer_app.offline.auto_total_mb' => 1]);

    $owner   = phoneAppOfficer();
    $officer = phoneAppOfficer();

    $open   = Folder::create(['name' => 'Resolutions']);
    $locked = Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => $owner->id]);
    $stored = Folder::create(['name' => 'Old Minutes', 'school_year' => '2024-2025', 'is_archived' => true]);

    $older  = phoneAppFile($open, 'older.pdf', ['size' => 800 * 1024, 'updated_at' => now()->subDay()]);
    $newer  = phoneAppFile($open, 'newer.pdf', ['size' => 300 * 1024]);
    $small  = phoneAppFile($open, 'small.pdf', ['size' => 50 * 1024, 'updated_at' => now()->subDays(2)]);
    phoneAppFile($open, 'video-sized.pdf', ['size' => 5 * 1048576]);        // over the per-file limit
    phoneAppFile($locked, 'ledger.pdf');                                      // restricted folder
    phoneAppFile($stored, 'minutes-2024.pdf', ['is_archived' => true]);      // past school year

    // Even the folder's owner is not sent a restricted file automatically.
    $ids = $this->actingAs($owner)->postJson(route('app.sync'), ['have' => []])
        ->assertOk()
        ->assertJsonPath('expire_days', config('officer_app.offline.expire_days'))
        ->json('files.*.id');

    // Newest first; "older" would take the total past 1 MB, the smaller one after it still fits.
    expect($ids)->toBe([$newer->id, $small->id]);

    expect($this->actingAs($officer)->postJson(route('app.sync'), ['have' => []])->json('files.*.name'))
        ->toBe(['newer.pdf', 'small.pdf'])
        ->and($older->exists)->toBeTrue();
});

test('the phone is told to drop files the officer can no longer open', function () {
    $owner   = phoneAppOfficer();
    $officer = phoneAppOfficer();

    $open   = Folder::create(['name' => 'Resolutions']);
    $locked = Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => $owner->id]);

    $kept     = phoneAppFile($open, 'kept.pdf');
    $nowLocked = phoneAppFile($locked, 'ledger.pdf');
    $trashed  = phoneAppFile($open, 'trashed.pdf');
    $trashed->moveToTrash();

    $have = [$kept->id, $nowLocked->id, $trashed->id, 99999];

    $this->actingAs($officer)->postJson(route('app.sync'), ['have' => $have])
        ->assertOk()
        ->assertJsonPath('revoked', [$nowLocked->id, $trashed->id, 99999]);

    // The owner still has access to the restricted one.
    $this->actingAs($owner)->postJson(route('app.sync'), ['have' => $have])
        ->assertJsonPath('revoked', [$trashed->id, 99999]);
});

test('offline saves are written to the activity log', function () {
    $officer = phoneAppOfficer();
    $file    = phoneAppFile(Folder::create(['name' => 'Resolutions']), 'Resolution No. 12.pdf');

    $locked = phoneAppFile(Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => phoneAppOfficer()->id]), 'ledger.pdf');

    // One report from the phone: 3 saved automatically, 2 chosen by hand -
    // one of which this officer cannot open, so it is not logged as theirs.
    $this->actingAs($officer)->postJson(route('app.sync.saved'), ['count' => 3, 'kept' => [$file->id, $locked->id]])->assertOk();

    expect(ActivityLog::pluck('activity')->all())
        ->toContain('Kept file offline on a phone: Resolution No. 12.pdf')
        ->toContain('Saved 3 files on a phone for offline use')
        ->not->toContain('Kept file offline on a phone: ledger.pdf');
});

test('a file added from the phone goes through the normal upload', function () {
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);

    $officer = phoneAppOfficer();
    $folder  = Folder::create(['name' => 'Resolutions']);
    $stored  = Folder::create(['name' => 'Old Minutes', 'school_year' => '2024-2025', 'is_archived' => true]);

    $this->actingAs($officer)->get(route('app.folder.add', $folder->id))
        ->assertOk()
        ->assertSee('Take a photo')
        ->assertSee('Scan as document');

    $this->actingAs($officer)->post(route('app.folder.store', $folder->id), [
        'files'            => [UploadedFile::fake()->image('photo.jpg')],
        'pending_approval' => 1,
    ])->assertRedirect(route('app.folder', $folder->id))->assertSessionHas('success');

    $file = File::where('filename', 'photo.jpg')->first();

    expect($file->folder_id)->toBe($folder->id)
        ->and($file->is_pending_approval)->toBeTrue()
        ->and($file->school_year)->toBe('2026-2027');

    Storage::disk('local')->assertExists($file->local_path);

    // Read-only folders can't be added to, by form or by a direct post.
    $this->actingAs($officer)->get(route('app.folder.add', $stored->id))
        ->assertRedirect(route('app.folder', $stored->id));

    $this->actingAs($officer)->post(route('app.folder.store', $stored->id), [
        'files' => [UploadedFile::fake()->image('late.jpg')],
    ])->assertSessionHas('error');

    expect(File::where('filename', 'late.jpg')->exists())->toBeFalse();
});

test('search in the app finds files by their text, including inside archives', function () {
    $officer = phoneAppOfficer();

    $live = Folder::create(['name' => 'Resolutions']);
    phoneAppFile($live, 'resolution-12.pdf', ['ocr_text' => 'Approved the intramurals budget.']);

    $old = Folder::create(['name' => 'Old Minutes']);
    phoneAppFile($old, 'minutes-august.pdf', ['ocr_text' => 'The intramurals committee reported.']);
    app(FolderArchiver::class)->archive($old->fresh(), 'Tester');

    $this->actingAs($officer)->get(route('app.search', ['query' => 'intramurals']))
        ->assertOk()
        ->assertSee('resolution-12.pdf')
        ->assertSee('Found in document text')
        ->assertSee('Files in archived folders')
        ->assertSee('minutes-august.pdf');

    $this->actingAs($officer)->get(route('app.search'))
        ->assertOk()
        ->assertSee('Search everything you can open');
});

test('a notification about a folder opens that folder in the app', function () {
    $owner  = phoneAppOfficer();
    $folder = Folder::create(['name' => 'Resolutions', 'created_by' => $owner->id]);

    $owner->notifyNow(new FileUploadedToYourFolder($folder->id, $folder->name, 'Someone', 1, 'draft.pdf'));

    $this->actingAs($owner)->get(route('app.notifications'))
        ->assertOk()
        ->assertSee(route('app.folder', $folder->id), false)
        ->assertDontSee('Opens on the website');

    $this->actingAs($owner)->get(route('app.home'))
        ->assertOk()
        ->assertSee('Notifications, 1 unread', false);
});

test('sign out and the officer\'s details live on the account screen', function () {
    $officer = phoneAppOfficer();

    $this->actingAs($officer)->get(route('app.account'))
        ->assertOk()
        ->assertSee($officer->name)
        ->assertSee('Secretary')
        ->assertSee('Sign out');

    $this->actingAs($officer)->get(route('app.home'))
        ->assertOk()
        ->assertSee(route('app.account'), false)
        ->assertDontSee('Sign out');
});

test('the offline files screen renders without needing any data from the server', function () {
    $this->actingAs(phoneAppOfficer())->get(route('app.offline'))
        ->assertOk()
        ->assertSee('Save files automatically')
        ->assertSee('officer-sw.js', false);
});
