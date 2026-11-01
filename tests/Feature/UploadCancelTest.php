<?php

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Uploads queue OCR; the cloud disk must never be reached from a test.
    Bus::fake();
    Storage::fake('local');
    Storage::fake('cloud');
});

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function cancelUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** Uploads one file the way the upload toast does and returns its reply. */
function toastUpload($test, User $user, Folder $folder, string $name = 'CITCS PayTrack SIA 4.docx')
{
    return $test->actingAs($user)->postJson(route('files.store'), [
        'files'     => [UploadedFile::fake()->createWithContent($name, 'contents of ' . $name)],
        'folder_id' => $folder->id,
    ])->assertOk();
}

test('an upload cancelled while the server was saving it is taken back, cloud copy and all', function () {
    $user = cancelUser();
    $folder = Folder::create(['name' => 'Capstone']);

    $cancelUrl = toastUpload($this, $user, $folder)->json('cancel_url');
    $file = File::where('filename', 'CITCS PayTrack SIA 4.docx')->first();
    expect($cancelUrl)->toBeString()
        ->and(Storage::disk('local')->exists($file->local_path))->toBeTrue()
        ->and(Storage::disk('cloud')->exists($file->filepath))->toBeTrue();

    $this->actingAs($user)->postJson($cancelUrl)->assertOk()->assertJson(['removed' => 1]);

    expect(File::withTrashed()->find($file->id))->toBeNull()
        ->and(Storage::disk('local')->exists($file->local_path))->toBeFalse()
        ->and(Storage::disk('cloud')->exists($file->filepath))->toBeFalse()
        ->and(ActivityLog::where('activity', 'Cancelled upload of 1 file(s): CITCS PayTrack SIA 4.docx')->count())->toBe(1);
});

test('the take-back link works only for the officer who uploaded, for those files, unaltered', function () {
    $user = cancelUser();
    $folder = Folder::create(['name' => 'Capstone']);
    toastUpload($this, $user, $folder, 'keep-me.docx');
    $keep = File::where('filename', 'keep-me.docx')->first();

    $cancelUrl = toastUpload($this, $user, $folder)->json('cancel_url');

    // Someone else holding the link.
    $this->actingAs(cancelUser())->postJson($cancelUrl)->assertForbidden();

    // The link changed to point at another file: the signature no longer matches.
    $tampered = preg_replace('/ids=\d+/', 'ids=' . $keep->id, $cancelUrl);
    $this->actingAs($user)->postJson($tampered)->assertForbidden();

    // After 15 minutes it no longer works.
    $this->travel(16)->minutes();
    $this->actingAs($user)->postJson($cancelUrl)->assertForbidden();

    expect(File::where('filename', 'keep-me.docx')->exists())->toBeTrue()
        ->and(File::where('filename', 'CITCS PayTrack SIA 4.docx')->exists())->toBeTrue();
});

test('a plain form upload from the phone app gets no take-back link', function () {
    $user = cancelUser();
    $folder = Folder::create(['name' => 'Capstone']);

    $this->actingAs($user)->post(route('files.store'), [
        'files'     => [UploadedFile::fake()->createWithContent('phone.pdf', "%PDF-1.4\n")],
        'folder_id' => $folder->id,
    ])->assertRedirect();

    expect(File::where('filename', 'phone.pdf')->exists())->toBeTrue();
});
