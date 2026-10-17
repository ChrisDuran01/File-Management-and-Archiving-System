<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\CloudFileUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Storage::fake('public');
    Storage::fake('local');
    Storage::fake('cloud');
});

/** An ordinary officer: an active term in a position that isn't Adviser/President. */
function privateStorageOfficer(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

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

/** A file whose cloud upload never completed - only its local copy exists. */
function privateStorageLocalOnlyFile(Folder $folder, string $filename): File
{
    $localPath = 'uploads/' . $folder->id . '/' . uniqid() . '_' . $filename;
    Storage::disk('local')->put($localPath, 'contents of ' . $filename);

    $file = new File();
    $file->forceFill([
        'folder_id'    => $folder->id,
        'filename'     => $filename,
        'filepath'     => uniqid() . '_' . $filename,
        'local_path'   => $localPath,
        'size'         => 100,
        'storage_type' => 'local',
    ])->save();

    return $file->fresh();
}

test('an uploaded file is kept where the web server cannot hand it out', function () {
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);

    $folder = Folder::create(['name' => 'Proposals']);

    $this->actingAs(privateStorageOfficer())->post(route('files.store'), [
        'files'     => [UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf')],
        'folder_id' => $folder->id,
    ])->assertSessionHas('success');

    $file = File::where('filename', 'draft.pdf')->first();

    Storage::disk('local')->assertExists($file->local_path);
    Storage::disk('public')->assertMissing($file->local_path);
});

test('a local copy is served only to someone who can open the file', function () {
    $creator = privateStorageOfficer();
    $other   = privateStorageOfficer();

    $folder = Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => $creator->id]);
    $file   = privateStorageLocalOnlyFile($folder, 'ledger.pdf');

    $this->get(route('files.raw', $file->id))->assertRedirect();

    $this->actingAs($other)->get(route('files.raw', $file->id))->assertForbidden();

    $this->actingAs($creator)->get(route('files.raw', $file->id))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="ledger.pdf"');
});

test('previewing a local-only file uses the protected link, never a storage one', function () {
    $officer = privateStorageOfficer();
    $folder  = Folder::create(['name' => 'Proposals']);
    $file    = privateStorageLocalOnlyFile($folder, 'proposal.pdf');

    $this->actingAs($officer)->get(route('files.preview', $file->id))
        ->assertOk()
        ->assertSee(route('files.raw', $file->id), false)
        ->assertDontSee('/storage/uploads', false);
});

test('a local-only office file offers a download instead of the online viewer', function () {
    $officer = privateStorageOfficer();
    $folder  = Folder::create(['name' => 'Proposals']);
    $file    = privateStorageLocalOnlyFile($folder, 'budget.xlsx');

    $this->actingAs($officer)->get(route('files.preview', $file->id))
        ->assertOk()
        ->assertDontSee('view.officeapps.live.com', false)
        ->assertSee('Download file');
});
