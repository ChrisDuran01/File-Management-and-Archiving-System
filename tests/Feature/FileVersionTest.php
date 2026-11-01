<?php

use App\Http\Controllers\BackupController;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\FileShareLink;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use App\Services\DuplicateDetector;
use App\Services\FolderArchiver;
use App\Services\TrashPurger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Uploads queue OCR; the cloud disk is the fake one.
    Bus::fake();
    Storage::fake('local');
    Storage::fake('cloud');
});

const FV_FIRST = "%PDF-1.4\n% SG Executive Resolution No. 22, first draft, wrong margins\n";
const FV_FIXED = "%PDF-1.4\n% SG Executive Resolution No. 22, margins fixed per Adviser\n";
const FV_TEXT = 'Resolution requesting additional water stations in every building. Whereas students walk '
    . 'between buildings in the heat; whereas the student government shall represent the students; be it '
    . 'resolved that the administration install water stations before the intramurals.';

function fvUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An ordinary officer: an active term in a position that isn't SuperAdmin. */
function fvOfficer(): User
{
    $user = fvUser();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert([
        'user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active',
    ]);

    return $user;
}

function fvPdf(string $name, string $bytes): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $bytes);
}

/** Uploads the first draft the way the folder page does. */
function fvUploadFirst($test, User $user, Folder $folder, string $name = 'Resolution-No-022.pdf'): File
{
    $test->actingAs($user)->postJson(route('files.store'), [
        'files' => [fvPdf($name, FV_FIRST)], 'folder_id' => $folder->id,
    ])->assertOk();

    return File::where('filename', $name)->sole();
}

function fvVersion($test, User $user, File $file, string $bytes = FV_FIXED, string $note = 'Fixed margins per Adviser', ?string $name = null)
{
    return $test->actingAs($user)->postJson(route('files.versions.store', $file->id), [
        'file' => fvPdf($name ?? $file->filename, $bytes), 'note' => $note,
    ]);
}

test('a name already in the folder is offered as the next version', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $file = fvUploadFirst($this, $user, $folder);

    $this->actingAs($user)->postJson(route('files.nameCheck'), [
        'folder_id' => $folder->id, 'names' => ['Resolution-No-022.pdf', 'Resolution-No-023.pdf'],
    ])->assertOk()
        ->assertJsonPath('folder', 'Resolution')
        ->assertJsonCount(1, 'existing')
        ->assertJsonPath('existing.0.name', 'Resolution-No-022.pdf')
        ->assertJsonPath('existing.0.next_version', 2)
        ->assertJsonPath('existing.0.final_version', null)
        ->assertJsonPath('existing.0.upload_url', route('files.versions.store', $file->id));
});

test('saving as a version keeps one row and the version before it', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $file = fvUploadFirst($this, $user, $folder);
    $first = $file->only(['filepath', 'local_path', 'content_hash']);

    fvVersion($this, $user, $file)->assertOk()
        ->assertJsonPath('version_no', 2)
        ->assertJsonPath('message', 'Saved as version 2 of “Resolution-No-022.pdf”.');

    $file->refresh();
    $old = FileVersion::where('file_id', $file->id)->sole();

    expect(File::where('folder_id', $folder->id)->count())->toBe(1)
        ->and($file->version_no)->toBe(2)
        ->and($file->filename)->toBe('Resolution-No-022.pdf')
        ->and($file->version_note)->toBe('Fixed margins per Adviser')
        ->and($file->version_by)->toBe($user->id)
        ->and($file->content_hash)->toBe(hash('sha256', FV_FIXED))
        ->and($file->filepath)->not->toBe($first['filepath'])
        ->and($old->version_no)->toBe(1)
        ->and($old->only(['filepath', 'local_path', 'content_hash']))->toBe($first);

    // Nothing was deleted: both versions' bytes are on this computer and in the cloud.
    Storage::disk('local')->assertExists([$first['local_path'], $file->local_path]);
    Storage::disk('cloud')->assertExists([$first['filepath'], $file->filepath]);
    expect(Storage::disk('local')->get($first['local_path']))->toBe(FV_FIRST)
        ->and(ActivityLog::where('activity', 'Uploaded version 2 of Resolution-No-022.pdf: Fixed margins per Adviser')->exists())->toBeTrue();

    $this->actingAs($user)->get(route('folders.show', $folder->id))
        ->assertOk()
        ->assertSee('v2')
        ->assertSee('Versions (2)')
        ->assertSee('Upload new version');
});

test('the Versions list shows every version with who, when and the note', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $file = fvUploadFirst($this, $user, $folder);
    fvVersion($this, $user, $file);

    $this->actingAs($user)->getJson(route('files.versions.index', $file->id))
        ->assertOk()
        ->assertJsonPath('version_no', 2)
        ->assertJsonPath('can_change', true)
        ->assertJsonPath('missing', 0)
        ->assertJsonCount(2, 'versions')
        ->assertJsonPath('versions.0.no', 2)
        ->assertJsonPath('versions.0.current', true)
        ->assertJsonPath('versions.0.by', $user->name)
        ->assertJsonPath('versions.0.note', 'Fixed margins per Adviser')
        ->assertJsonPath('versions.0.restore_url', null)
        ->assertJsonPath('versions.1.no', 1)
        ->assertJsonPath('versions.1.current', false)
        ->assertJsonPath('versions.1.note', null)
        ->assertJsonPath('versions.1.restore_url', route('files.versions.restore', [$file->id, 1]));
});

test('an older version can be opened and downloaded; the current one opens as usual', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $file = fvUploadFirst($this, $user, $folder);
    fvVersion($this, $user, $file);

    $download = $this->actingAs($user)->get(route('files.versions.download', [$file->id, 1]))->assertOk();
    expect(file_get_contents($download->baseResponse->getFile()->getPathname()))->toBe(FV_FIRST)
        ->and($download->headers->get('Content-Disposition'))->toContain('attachment');

    $this->actingAs($user)->get(route('files.versions.show', [$file->id, 1]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($user)->get(route('files.versions.show', [$file->id, 2]))
        ->assertRedirect(route('files.preview', $file->id));
    $this->actingAs($user)->get(route('files.versions.show', [$file->id, 9]))->assertNotFound();
});

test('make current brings an old version back as a new one, with its own copy of the bytes', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $file = fvUploadFirst($this, $user, $folder);
    fvVersion($this, $user, $file);

    $this->actingAs($user)->postJson(route('files.versions.restore', [$file->id, 1]))
        ->assertOk()->assertJsonPath('message', 'Version 1 is current again, saved as version 3.');

    $file->refresh();
    $v1 = FileVersion::where('file_id', $file->id)->where('version_no', 1)->sole();

    expect($file->version_no)->toBe(3)
        ->and($file->version_note)->toBe('Restored from version 1')
        ->and($file->content_hash)->toBe(hash('sha256', FV_FIRST))
        ->and(FileVersion::where('file_id', $file->id)->pluck('version_no')->sort()->values()->all())->toBe([1, 2])
        ->and($file->local_path)->not->toBe($v1->local_path)
        ->and($file->filepath)->not->toBe($v1->filepath);
    Storage::disk('local')->assertExists($v1->local_path);
});

test('the final mark stays on the version that was accepted', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $file = fvUploadFirst($this, $user, $folder);
    fvVersion($this, $user, $file);
    DB::table('files')->where('id', $file->id)->update(['updated_at' => '2026-10-27 09:05:00']);

    $this->actingAs($user)->postJson(route('files.final', $file->id))->assertOk();

    $file->refresh();
    expect($file->final_version_no)->toBe(2)
        ->and($file->isFinal())->toBeTrue()
        ->and($file->final_by)->toBe($user->id)
        ->and($file->updated_at->format('Y-m-d H:i'))->toBe('2026-10-27 09:05');   // not a change to the file

    $this->actingAs($user)->get(route('folders.show', $folder->id))->assertSee('Final')->assertSee('Remove final mark');
    $this->actingAs($user)->postJson(route('files.nameCheck'), ['folder_id' => $folder->id, 'names' => [$file->filename]])
        ->assertJsonPath('existing.0.final_version', 2);

    fvVersion($this, $user, $file, "%PDF-1.4\n% signed copy\n", 'Signed copy')->assertOk();

    $file->refresh();
    expect($file->version_no)->toBe(3)
        ->and($file->final_version_no)->toBe(2)
        ->and($file->isFinal())->toBeFalse();

    $this->actingAs($user)->getJson(route('files.versions.index', $file->id))
        ->assertJsonPath('versions.1.no', 2)
        ->assertJsonPath('versions.1.final', true)
        ->assertJsonPath('versions.0.final', false);

    $this->actingAs($user)->deleteJson(route('files.final.clear', $file->id))->assertOk();
    expect($file->fresh()->final_version_no)->toBeNull();
});

test('a file under a past school year shows its versions but can\'t be changed', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $file = fvUploadFirst($this, $user, $folder);
    $file->forceFill(['is_archived' => true])->save();

    $this->actingAs($user)->getJson(route('files.versions.index', $file->id))
        ->assertOk()->assertJsonPath('can_change', false);

    fvVersion($this, $user, $file)->assertStatus(422);
    $this->actingAs($user)->postJson(route('files.final', $file->id))->assertStatus(422);
    $this->actingAs($user)->postJson(route('files.nameCheck'), ['folder_id' => $folder->id, 'names' => [$file->filename]])
        ->assertJsonCount(0, 'existing');

    expect($file->fresh()->version_no)->toBe(1)
        ->and(FileVersion::count())->toBe(0);
});

test('a restricted folder keeps its versions to those who may open it', function () {
    $owner = fvOfficer();
    $folder = Folder::create(['name' => 'Disciplinary cases', 'is_restricted' => true, 'created_by' => $owner->id]);
    $file = fvUploadFirst($this, $owner, $folder);
    fvVersion($this, $owner, $file)->assertOk();

    $other = fvOfficer();
    $this->actingAs($other)->getJson(route('files.versions.index', $file->id))->assertForbidden();
    $this->actingAs($other)->get(route('files.versions.download', [$file->id, 1]))->assertForbidden();
    fvVersion($this, $other, $file)->assertForbidden();
});

test('a new version may change the file type, unless that name is already taken', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $word = new File();
    $word->forceFill([
        'folder_id' => $folder->id, 'filename' => 'Resolution-No-022.docx', 'filepath' => 'word_' . uniqid() . '.docx',
        'size' => 1024, 'storage_type' => 'both',
    ])->save();

    fvVersion($this, $user, $word, FV_FIXED, 'Signed copy', 'scan0001.pdf')->assertOk();
    expect($word->fresh()->filename)->toBe('Resolution-No-022.pdf')
        ->and(FileVersion::where('file_id', $word->id)->value('filename'))->toBe('Resolution-No-022.docx');

    $other = new File();
    $other->forceFill([
        'folder_id' => $folder->id, 'filename' => 'Resolution-No-023.docx', 'filepath' => 'word_' . uniqid() . '.docx',
        'size' => 1024, 'storage_type' => 'both',
    ])->save();
    $taken = new File();
    $taken->forceFill([
        'folder_id' => $folder->id, 'filename' => 'Resolution-No-023.pdf', 'filepath' => 'pdf_' . uniqid() . '.pdf',
        'size' => 1024, 'storage_type' => 'both',
    ])->save();

    fvVersion($this, $user, $other, FV_FIXED, '', 'scan0002.pdf')
        ->assertStatus(422)
        ->assertJsonPath('message', '“Resolution-No-023.pdf” is already in this folder as a separate file. Rename one of them first.');
    expect($other->fresh()->version_no)->toBe(1);
});

test('a newer draft saved under another name can become the original\'s next version', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $original = fvUploadFirst($this, $user, $folder);
    $original->forceFill(['ocr_text' => FV_TEXT, 'created_at' => now()->subDay()])->save();

    $this->actingAs($user)->postJson(route('files.store'), [
        'files' => [fvPdf('Reso-22-revised.pdf', FV_FIXED)], 'folder_id' => $folder->id,
    ])->assertOk();
    $draft = File::where('filename', 'Reso-22-revised.pdf')->sole();
    $draft->forceFill(['ocr_text' => FV_TEXT . ' Signatory titles fixed.', 'ocr_status' => 'done'])->save();

    $detector = app(DuplicateDetector::class);
    foreach ([$original, $draft] as $f) {
        $detector->indexText($f->fresh());
    }
    expect($detector->check($draft->fresh())?->id)->toBe($original->id);

    $link = FileShareLink::createFor($draft->fresh(), $user, 7);
    $draftPaths = $draft->fresh()->only(['filepath', 'local_path']);
    $originalPaths = $original->fresh()->only(['filepath', 'local_path']);

    $this->actingAs($user)->get(route('folders.show', $folder->id))
        ->assertOk()
        ->assertSee('Possibly a newer draft')
        ->assertSee('Make it version 2 of the original');

    $this->actingAs($user)->post(route('file.mergeIntoOriginal', $draft->id))->assertSessionHas('success');

    $original->refresh();
    expect(File::withTrashed()->find($draft->id))->toBeNull()
        ->and($original->version_no)->toBe(2)
        ->and($original->only(['filepath', 'local_path']))->toBe($draftPaths)
        ->and($original->ocr_text)->toBe(FV_TEXT . ' Signatory titles fixed.')
        ->and($original->version_note)->toBe('Was “Reso-22-revised.pdf”')
        ->and(FileVersion::where('file_id', $original->id)->sole()->only(['filepath', 'local_path']))->toBe($originalPaths)
        ->and($link->fresh()->file_id)->toBe($original->id);
    Storage::disk('local')->assertExists([$draftPaths['local_path'], $originalPaths['local_path']]);
});

test('an exact copy is not offered as a version: it is just a copy', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    fvUploadFirst($this, $user, $folder);

    $this->actingAs($user)->postJson(route('files.store'), [
        'files' => [fvPdf('Resolution-No-022 - Copy.pdf', FV_FIRST)], 'folder_id' => $folder->id,
    ])->assertOk();
    $copy = File::where('filename', 'Resolution-No-022 - Copy.pdf')->sole();

    $this->actingAs($user)->get(route('folders.show', $folder->id))
        ->assertSee('Possible copy')->assertDontSee('Make it version');
    $this->actingAs($user)->post(route('file.mergeIntoOriginal', $copy->id))->assertSessionHas('error');
    expect($copy->fresh())->not->toBeNull();
});

test('deleting a file forever removes its older versions too', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $file = fvUploadFirst($this, $user, $folder);
    fvVersion($this, $user, $file);
    $old = FileVersion::where('file_id', $file->id)->sole();

    $file->refresh()->moveToTrash();
    app(TrashPurger::class)->purgeFile(File::withTrashed()->find($file->id));

    Storage::disk('local')->assertMissing($old->local_path);
    Storage::disk('cloud')->assertMissing($old->filepath);
    expect(FileVersion::count())->toBe(0);
});

test('a backup names older versions on their own and restores them to their own path', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution', 'school_year' => '2026-2027']);
    $file = fvUploadFirst($this, $user, $folder);
    fvVersion($this, $user, $file);
    $old = FileVersion::where('file_id', $file->id)->sole();

    // Both are private steps of a backup and a restore.
    $backup = app(BackupController::class);
    $names = (function (array $files) { return $this->buildZipEntryNames($files); })
        ->call($backup, [['name' => $old->filepath], ['name' => $file->fresh()->filepath]]);

    expect($names[$old->filepath])->toBe('versions/2026-2027/Resolution/' . $old->id . '_Resolution-No-022 (v1).pdf')
        ->and($names[$file->fresh()->filepath])->toStartWith('folders/');

    $resolve = fn (string $entry) => (function () use ($entry) { return $this->resolveOriginalCloudPath($entry); })->call($backup);
    expect($resolve($names[$old->filepath]))->toBe($old->filepath)
        ->and($resolve($names[$file->fresh()->filepath]))->toBe($file->fresh()->filepath);
});

test('versions saved while the cloud was unreachable are sent later', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution']);
    $file = fvUploadFirst($this, $user, $folder);
    fvVersion($this, $user, $file);
    $old = FileVersion::where('file_id', $file->id)->sole();
    Storage::disk('cloud')->delete($old->filepath);
    $old->forceFill(['storage_type' => 'local'])->save();

    $this->artisan('files:sync-to-cloud')->assertSuccessful();

    expect($old->fresh()->storage_type)->toBe('both');
    Storage::disk('cloud')->assertExists($old->filepath);
});

test('archiving a folder keeps older versions inside its ZIP', function () {
    $user = fvOfficer();
    $folder = Folder::create(['name' => 'Resolution', 'is_restricted' => false]);
    $file = fvUploadFirst($this, $user, $folder);
    fvVersion($this, $user, $file);
    $old = FileVersion::where('file_id', $file->id)->sole();

    $result = app(FolderArchiver::class)->archive($folder, 'Tester');
    expect($result['success'])->toBeTrue();

    $zipPath = Storage::disk('local')->path(Storage::disk('local')->files('archives')[0]);
    $zip = new ZipArchive();
    $zip->open($zipPath);

    expect($zip->getFromName('Resolution-No-022.pdf'))->toBe(FV_FIXED)
        ->and($zip->getFromName('Older versions/Resolution-No-022 (v1).pdf'))->toBe(FV_FIRST);
    $zip->close();

    Storage::disk('local')->assertMissing($old->local_path);
    expect(FileVersion::count())->toBe(0);
});
