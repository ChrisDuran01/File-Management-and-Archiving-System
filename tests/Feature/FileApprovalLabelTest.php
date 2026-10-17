<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\CloudFileUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Uploads and "Make Public" queue OCR, local caching and student push.
    Bus::fake();
});

/** A signed-in-ready user who is past the password-change and privacy screens. */
function approvalUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function approvalSuperAdmin(): User
{
    return approvalUser();
}

/** An ordinary officer: an active term in a position that isn't Adviser/President. */
function approvalOfficer(): User
{
    $user = approvalUser();

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

function approvalFile(array $attributes = []): File
{
    $file = new File();
    $file->forceFill(array_merge([
        'folder_id'    => $attributes['folder_id'] ?? Folder::create(['name' => 'General'])->id,
        'filename'     => 'proposal.pdf',
        'filepath'     => 'proposal_' . uniqid() . '.pdf',
        'size'         => 2048,
        'storage_type' => 'cloud',
    ], $attributes))->save();

    return $file->fresh();
}

test('a file uploaded with the box ticked is labelled as subject for approval', function () {
    $user   = approvalOfficer();
    $folder = Folder::create(['name' => 'Proposals']);
    Cache::put('pending_upload:draft_1.pdf', $user->id, now()->addHour());
    Cache::put('pending_upload:final_1.pdf', $user->id, now()->addHour());

    $this->actingAs($user)->postJson(route('files.confirmUpload'), [
        'filename' => 'draft.pdf', 'cloudPath' => 'draft_1.pdf', 'size' => 100,
        'folder_id' => $folder->id, 'pending_approval' => 1,
    ])->assertOk();

    $this->actingAs($user)->postJson(route('files.confirmUpload'), [
        'filename' => 'final.pdf', 'cloudPath' => 'final_1.pdf', 'size' => 100,
        'folder_id' => $folder->id, 'pending_approval' => 0,
    ])->assertOk();

    expect(File::where('filename', 'draft.pdf')->first()->is_pending_approval)->toBeTrue()
        ->and(File::where('filename', 'final.pdf')->first()->is_pending_approval)->toBeFalse();
});

test('the server-relayed upload path carries the label too', function () {
    Storage::fake('local');
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);

    $folder = Folder::create(['name' => 'Proposals']);

    $this->actingAs(approvalOfficer())->post(route('files.store'), [
        'files'            => [UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf')],
        'folder_id'        => $folder->id,
        'pending_approval' => 1,
    ])->assertSessionHas('success');

    expect(File::where('filename', 'draft.pdf')->first()->is_pending_approval)->toBeTrue();
});

test('a file subject for approval cannot be made public', function () {
    $file = approvalFile(['is_pending_approval' => true]);

    $this->actingAs(approvalSuperAdmin())
        ->post(route('file.toggleAccess', $file->id))
        ->assertSessionHas('error');

    expect((bool) $file->fresh()->is_public)->toBeFalse();
});

test('a file subject for approval is never shown to students', function () {
    $official = approvalFile(['is_public' => 1]);
    // Reachable only by bypassing the UI, but the student page must hold anyway.
    $draft = approvalFile(['is_public' => 1, 'is_pending_approval' => true]);

    expect(File::publiclyVisible()->pluck('id')->all())->toBe([$official->id]);

    $this->get(route('files.previewStudentDashboard', $draft->id))->assertForbidden();
});

test('any officer can add the label but only the folder creator or a SuperAdmin can approve', function () {
    $creator = approvalOfficer();
    $other   = approvalOfficer();
    $folder  = Folder::create(['name' => 'Proposals', 'created_by' => $creator->id]);
    $file    = approvalFile(['folder_id' => $folder->id]);

    $this->actingAs($other)
        ->post(route('file.setApproval', $file->id), ['pending' => 1])
        ->assertSessionHas('success');
    expect($file->fresh()->is_pending_approval)->toBeTrue();

    $this->actingAs($other)
        ->post(route('file.setApproval', $file->id), ['pending' => 0])
        ->assertSessionHas('error');
    expect($file->fresh()->is_pending_approval)->toBeTrue();

    $this->actingAs($creator)
        ->post(route('file.setApproval', $file->id), ['pending' => 0])
        ->assertSessionHas('success');
    expect($file->fresh()->is_pending_approval)->toBeFalse();

    $file->forceFill(['is_pending_approval' => true])->save();

    $this->actingAs(approvalSuperAdmin())
        ->post(route('file.setApproval', $file->id), ['pending' => 0])
        ->assertSessionHas('success');
    expect($file->fresh()->is_pending_approval)->toBeFalse();
});

test('labelling a public file takes it off the student page, and needs manage rights', function () {
    $creator = approvalOfficer();
    $other   = approvalOfficer();
    $folder  = Folder::create(['name' => 'Resolutions', 'created_by' => $creator->id]);
    $file    = approvalFile(['folder_id' => $folder->id, 'is_public' => 1]);

    $this->actingAs($other)
        ->post(route('file.setApproval', $file->id), ['pending' => 1])
        ->assertSessionHas('error');
    expect($file->fresh()->is_pending_approval)->toBeFalse()
        ->and((bool) $file->fresh()->is_public)->toBeTrue();

    $this->actingAs($creator)
        ->post(route('file.setApproval', $file->id), ['pending' => 1])
        ->assertSessionHas('success');
    expect($file->fresh()->is_pending_approval)->toBeTrue()
        ->and((bool) $file->fresh()->is_public)->toBeFalse();
});

test('a file under a past school year keeps its label as it was', function () {
    $file = approvalFile(['is_pending_approval' => true, 'is_archived' => true]);

    $this->actingAs(approvalSuperAdmin())
        ->post(route('file.setApproval', $file->id), ['pending' => 0])
        ->assertSessionHas('error');

    expect($file->fresh()->is_pending_approval)->toBeTrue();
});

test('the label shows in the folder and on the opened file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/1/proposal.pdf', '%PDF-1.4');

    $creator = approvalOfficer();
    $other   = approvalOfficer();
    $folder  = Folder::create(['name' => 'Proposals', 'created_by' => $creator->id]);
    $draft   = approvalFile([
        'folder_id' => $folder->id, 'is_pending_approval' => true,
        'storage_type' => 'local', 'local_path' => 'uploads/1/proposal.pdf',
    ]);
    approvalFile(['folder_id' => $folder->id, 'filename' => 'minutes.pdf']);

    $this->actingAs($creator)->get(route('folders.show', $folder->id))
        ->assertOk()
        ->assertSee('1 subject for approval')
        ->assertSee('Mark as approved');

    // Someone who can't approve still sees the label, just not the action.
    $this->actingAs($other)->get(route('folders.show', $folder->id))
        ->assertOk()
        ->assertSee('1 subject for approval')
        ->assertDontSee('Mark as approved');

    $this->actingAs($creator)->get(route('files.preview', $draft->id))
        ->assertOk()
        ->assertSee('This file has not been approved yet')
        ->assertSee('Mark as approved');

    $this->actingAs($other)->get(route('files.preview', $draft->id))
        ->assertOk()
        ->assertSee('This file has not been approved yet')
        ->assertDontSee('Mark as approved');
});

test('the label follows the file into search and the folder list upload form', function () {
    $admin  = approvalSuperAdmin();
    $folder = Folder::create(['name' => 'Proposals', 'created_by' => $admin->id]);
    approvalFile(['folder_id' => $folder->id, 'filename' => 'intramurals-proposal.pdf', 'is_pending_approval' => true]);

    $this->actingAs($admin)->get(route('search', ['query' => 'intramurals']))
        ->assertOk()->assertSee('Subject for approval');

    $this->actingAs($admin)->get(route('search.live', ['query' => 'intramurals']))
        ->assertOk()->assertSee('subject for approval');

    $this->actingAs($admin)->get(route('folders.index'))
        ->assertOk()->assertSee('Still subject for approval');
});
