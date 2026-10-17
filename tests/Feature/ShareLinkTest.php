<?php

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\FileShareLink;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Storage::fake('local');
    Storage::fake('cloud');
});

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function shareSuperAdmin(): User
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
function shareOfficer(): User
{
    $user = shareSuperAdmin();

    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);

    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

function shareFile(Folder $folder, string $filename = 'Resolution No. 12.pdf'): File
{
    $localPath = 'uploads/' . $folder->id . '/' . uniqid() . '_' . $filename;
    Storage::disk('local')->put($localPath, 'contents of ' . $filename);

    $file = new File();
    $file->forceFill([
        'folder_id'    => $folder->id,
        'filename'     => $filename,
        'filepath'     => uniqid() . '_' . $filename,
        'local_path'   => $localPath,
        'size'         => 2048,
        'storage_type' => 'both',
    ])->save();

    return $file->fresh();
}

test('an officer makes a link, and someone without an account opens and downloads the file', function () {
    $officer = shareOfficer();
    $file    = shareFile(Folder::create(['name' => 'Resolutions']));

    $url = $this->actingAs($officer)
        ->postJson(route('share-links.store', $file->id), ['days' => 7])
        ->assertCreated()
        ->assertJsonPath('link.mine', true)
        ->json('link.url');

    expect(ActivityLog::where('activity', 'Made a share link for file: Resolution No. 12.pdf (works for 7 days)')->exists())->toBeTrue();

    $this->getJson(route('share-links.index', $file->id))
        ->assertOk()
        ->assertJsonPath('can_create', true)
        ->assertJsonCount(1, 'links');

    Auth::logout();
    $link = FileShareLink::first();

    // The token is stored only as a hash and encrypted - never as it is.
    expect(DB::table('file_share_links')->value('token'))->not->toBe($link->token);
    expect($url)->toBe(route('share.show', $link->token));

    $this->get($url)
        ->assertOk()
        ->assertSee('Resolution No. 12.pdf')
        ->assertSee('Shared by ' . $officer->name)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    // A chat app drawing the link's preview isn't someone opening it.
    $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get($url)->assertOk();

    $this->get(route('share.file', ['token' => $link->token, 'download' => 1]))
        ->assertOk()
        ->assertDownload('Resolution No. 12.pdf');

    expect($link->fresh()->open_count)->toBe(1);
    expect($link->fresh()->download_count)->toBe(1);

    // Counting the opens must not move the expiry (it did on MariaDB once -
    // see the migration). SQLite can't reproduce that, but the rule stands.
    expect($link->fresh()->expires_at->isSameMinute($link->expires_at))->toBeTrue();
});

test('a link stops working when it expires, is turned off, or the file goes to Trash', function () {
    $officer = shareOfficer();
    $file    = shareFile(Folder::create(['name' => 'Minutes']));

    $expiring = FileShareLink::createFor($file, $officer, 1);
    $this->travel(2)->days();
    $this->get(route('share.show', $expiring->token))->assertStatus(410)->assertSee('This link no longer works');
    $this->get(route('share.file', $expiring->token))->assertStatus(410);
    $this->travelBack();

    $turnedOff = FileShareLink::createFor($file, $officer, 7);
    $this->actingAs($officer)->deleteJson(route('share-links.destroy', $turnedOff->id))->assertOk();
    expect(ActivityLog::where('activity', 'Turned off a share link for file: Resolution No. 12.pdf')->exists())->toBeTrue();
    $this->get(route('share.show', $turnedOff->token))->assertStatus(410);
    $this->get(route('share.file', ['token' => $turnedOff->token, 'download' => 1]))
        ->assertStatus(410)
        ->assertSee('This link no longer works');

    $trashed = FileShareLink::createFor($file, $officer, 7);
    $this->get(route('share.show', $trashed->token))->assertOk();
    $file->moveToTrash();
    $this->get(route('share.show', $trashed->token))->assertStatus(410);

    // A link that never existed reads the same as one that stopped.
    $this->get(route('share.show', str_repeat('a', 40)))->assertNotFound()->assertSee('This link no longer works');
});

test('only the creator of a restricted folder or a SuperAdmin can share what is in it', function () {
    $owner   = shareOfficer();
    $allowed = shareOfficer();
    $other   = shareOfficer();

    $folder = Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => $owner->id]);
    $folder->allowedUsers()->attach($allowed->id);
    $file = shareFile($folder, 'Budget.pdf');

    // Can open it, but can't decide that people outside the system see it.
    $this->actingAs($allowed)->getJson(route('share-links.index', $file->id))
        ->assertOk()
        ->assertJsonPath('can_create', false);
    $this->actingAs($allowed)->postJson(route('share-links.store', $file->id), ['days' => 7])->assertForbidden();

    $this->actingAs($other)->getJson(route('share-links.index', $file->id))->assertForbidden();

    $this->actingAs($owner)->postJson(route('share-links.store', $file->id), ['days' => 7])->assertCreated();
    $this->actingAs(shareSuperAdmin())->postJson(route('share-links.store', $file->id), ['days' => 30])->assertCreated();

    // Only the lengths on offer.
    $this->actingAs($owner)->postJson(route('share-links.store', $file->id), ['days' => 365])->assertUnprocessable();
});

test('a link stops when the officer who made it could no longer make it', function () {
    $officer = shareOfficer();
    $owner   = shareOfficer();

    $folder = Folder::create(['name' => 'Projects', 'created_by' => $owner->id]);
    $file   = shareFile($folder, 'Plan.pdf');

    // The folder is restricted later, and this officer doesn't manage it.
    $link = FileShareLink::createFor($file, $officer, 7);
    $this->get(route('share.show', $link->token))->assertOk();
    $folder->update(['is_restricted' => true]);
    $this->get(route('share.show', $link->token))->assertStatus(410);
    $folder->update(['is_restricted' => false]);

    // The officer's term ends.
    $link = FileShareLink::createFor($file, $officer, 7);
    $this->get(route('share.show', $link->token))->assertOk();
    DB::table('officer_terms')->where('user_id', $officer->id)->update(['status' => 'inactive']);
    $this->get(route('share.show', $link->token))->assertStatus(410);
});

test('a link is turned off by the officer who made it or whoever manages the file', function () {
    $maker  = shareOfficer();
    $other  = shareOfficer();
    $owner  = shareOfficer();
    $file   = shareFile(Folder::create(['name' => 'Events', 'created_by' => $owner->id]), 'Program.pdf');

    $link = FileShareLink::createFor($file, $maker, 7);

    $this->actingAs($other)->getJson(route('share-links.index', $file->id))
        ->assertJsonPath('links.0.can_turn_off', false)
        ->assertJsonPath('links.0.made_by', $maker->name);
    $this->actingAs($other)->deleteJson(route('share-links.destroy', $link->id))->assertForbidden();
    expect($link->fresh()->revoked_at)->toBeNull();

    $this->actingAs($owner)->deleteJson(route('share-links.destroy', $link->id))->assertOk();
    expect($link->fresh()->revoked_at)->not->toBeNull();
    expect($link->fresh()->revoked_by)->toBe($owner->id);

    $this->actingAs($owner)->getJson(route('share-links.index', $file->id))->assertJsonCount(0, 'links');
});

test('the website offers Share on the folder and file pages, only to who may share', function () {
    $owner   = shareOfficer();
    $allowed = shareOfficer();

    $open       = Folder::create(['name' => 'Resolutions']);
    $restricted = Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => $owner->id]);
    $restricted->allowedUsers()->attach($allowed->id);
    $openFile   = shareFile($open);
    $lockedFile = shareFile($restricted, 'Budget.pdf');

    $this->actingAs($allowed)->get(route('folders.show', $open->id))
        ->assertOk()->assertSee('Share link')->assertSee('id="shareDialog"', false);
    $this->actingAs($allowed)->get(route('files.preview', $openFile->id))
        ->assertOk()->assertSee('class="btn-share"', false);

    $this->actingAs($allowed)->get(route('folders.show', $restricted->id))->assertOk()->assertDontSee('Share link');
    $this->actingAs($allowed)->get(route('files.preview', $lockedFile->id))->assertOk()->assertDontSee('class="btn-share"', false);

    $this->actingAs($owner)->get(route('folders.show', $restricted->id))->assertOk()->assertSee('Share link');
});

test('the phone app offers a link or a copy of the file, and logs each copy sent - even one sent offline', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36');
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(18, 0));

    $officer = shareOfficer();
    $file    = shareFile(Folder::create(['name' => 'Resolutions']));
    $locked  = shareFile(Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => shareOfficer()->id]), 'ledger.pdf');

    $this->actingAs($officer)->get(route('app.file', $file->id))
        ->assertOk()
        ->assertSee('data-share-open', false)
        ->assertSee('Send a link')
        ->assertSee('Send the file')
        ->assertSee('Send or open in another app');

    // Reported together once the phone is back online: one sent just now,
    // one over Bluetooth two hours earlier with no connection, and one this
    // officer can't open (not written under their name).
    $this->actingAs($officer)->postJson(route('app.sync.saved'), ['sent' => [
        ['id' => $file->id, 'at' => now()->subHours(2)->getTimestampMs()],
        ['id' => $file->id, 'at' => now()->getTimestampMs()],
        ['id' => $locked->id, 'at' => now()->getTimestampMs()],
    ]])->assertOk();

    expect(ActivityLog::pluck('activity')->all())
        ->toContain('Sent a copy of a file from a phone: Resolution No. 12.pdf (sent Oct 12, 4:00 PM)')
        ->toContain('Sent a copy of a file from a phone: Resolution No. 12.pdf')
        ->not->toContain('Sent a copy of a file from a phone: ledger.pdf');
});
