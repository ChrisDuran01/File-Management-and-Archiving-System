<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\CloudFileUploader;
use App\Services\DuplicateDetector;
use App\Services\TextVectors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Uploads queue OCR; the cloud disk must never be reached from a test.
    Bus::fake();
    Storage::fake('local');
    Storage::fake('cloud');
});

const MINUTES_TEXT = 'Minutes of the regular meeting of the student government held on July sixteen. '
    . 'The governor called the meeting to order. The secretary read the agenda: intramurals budget, '
    . 'polo shirts procurement, freshmen orientation schedule and the election calendar. '
    . 'Senators discussed the intramurals budget and approved the procurement of polo shirts.';

const RESOLUTION_TEXT = 'Resolution establishing student councils in every college. Whereas the constitution '
    . 'provides for councils; whereas the councils shall represent each college; be it resolved that '
    . 'each college shall elect a council chairperson, a treasurer and an auditor before September.';

function dupUser(): User
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
function dupOfficer(): User
{
    $user = dupUser();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert([
        'user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active',
    ]);

    return $user;
}

function dupFile(array $attributes = []): File
{
    $file = new File();
    $file->forceFill(array_merge([
        'folder_id'    => $attributes['folder_id'] ?? Folder::create(['name' => 'Minutes'])->id,
        'filename'     => 'minutes.docx',
        'filepath'     => 'minutes_' . uniqid() . '.docx',
        'size'         => 2048,
        'storage_type' => 'cloud',
    ], $attributes))->save();

    return $file->fresh();
}

/** What ExtractFileOcrText does once the text is read. */
function dupRead(File $file): ?File
{
    $detector = app(DuplicateDetector::class);
    $detector->indexText($file);

    return $detector->check($file);
}

test('words are lower-cased, and stopwords and plain numbers are left out', function () {
    expect(app(TextVectors::class)->tokenize('The Senators met at 10 and ANG governor signed 2026 minutes'))
        ->toBe(['senators', 'met', 'governor', 'minutes']);
});

test('the same text under another name is marked as a copy of the older file', function () {
    $folder = Folder::create(['name' => 'Minutes']);
    $older = dupFile(['folder_id' => $folder->id, 'filename' => '3-MOTM-E.M.-07-16-26.docx', 'ocr_text' => MINUTES_TEXT]);
    dupFile(['folder_id' => $folder->id, 'filename' => 'resolution-21.docx', 'ocr_text' => RESOLUTION_TEXT]);
    $newer = dupFile(['folder_id' => $folder->id, 'filename' => '3-MOTM-EM-07-16-26.pdf', 'ocr_text' => MINUTES_TEXT . ' Adjourned.']);

    expect(dupRead($older))->toBeNull();
    expect(dupRead($newer)?->id)->toBe($older->id);

    $newer->refresh();
    expect($newer->duplicate_of_id)->toBe($older->id)
        ->and($newer->duplicate_similarity)->toBeGreaterThanOrEqual(85)
        ->and($older->fresh()->duplicate_of_id)->toBeNull();
});

test('a file named like a copy is the copy, even when it was saved first', function () {
    $copy = dupFile(['filename' => '2-MOM-E.M.-07-15-26 - Copy.docx', 'ocr_text' => MINUTES_TEXT]);
    $original = dupFile(['filename' => '2-MOM-E.M.-07-15-26.docx', 'ocr_text' => MINUTES_TEXT]);

    dupRead($copy);
    dupRead($original);

    expect($copy->fresh()->duplicate_of_id)->toBe($original->id)
        ->and($original->fresh()->duplicate_of_id)->toBeNull();
});

test('the Word copy of a resolution points at its own PDF, not at a merely alike resolution', function () {
    $pdf = dupFile(['filename' => 'SG-Executive-Resolution-No-005.pdf', 'ocr_text' => RESOLUTION_TEXT . ' Number five. Approved.']);
    $other = dupFile(['filename' => 'SG-Executive-Resolution-No-006_.docx', 'ocr_text' => RESOLUTION_TEXT . ' Number five. Approved. Signed.']);
    $word = dupFile(['filename' => 'SG-Executive-Resolution-No-005.docx', 'ocr_text' => RESOLUTION_TEXT . ' Number five. Approved. Signed.']);

    foreach ([$pdf, $other, $word] as $f) {
        dupRead($f->fresh());
    }

    expect($word->fresh()->duplicate_of_id)->toBe($pdf->id);
});

test('different documents are not marked', function () {
    $minutes = dupFile(['ocr_text' => MINUTES_TEXT]);
    $resolution = dupFile(['filename' => 'resolution.docx', 'ocr_text' => RESOLUTION_TEXT]);

    dupRead($minutes);

    expect(dupRead($resolution))->toBeNull()
        ->and($resolution->fresh()->duplicate_of_id)->toBeNull();
});

test('checking a file does not change its Modified date', function () {
    $older = dupFile(['ocr_text' => MINUTES_TEXT]);
    $newer = dupFile(['filename' => 'copy.docx', 'ocr_text' => MINUTES_TEXT]);
    DB::table('files')->update(['updated_at' => '2026-08-01 09:00:00']);

    dupRead($older->fresh());
    dupRead($newer->fresh());

    expect($newer->fresh()->updated_at->format('Y-m-d H:i'))->toBe('2026-08-01 09:00')
        ->and($newer->fresh()->duplicate_of_id)->toBe($older->id);
});

test('an exact copy is named in the upload toast before its text is read', function () {
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);
    $user = dupOfficer();
    $folder = Folder::create(['name' => 'Resolutions']);
    $bytes = "%PDF-1.4\n% resolution no. 5, polo shirts\n";

    $this->actingAs($user)->postJson(route('files.store'), [
        'files' => [UploadedFile::fake()->createWithContent('Resolution-No-005.pdf', $bytes)], 'folder_id' => $folder->id,
    ])->assertOk()->assertJsonMissing(['copies']);

    $this->actingAs($user)->postJson(route('files.store'), [
        'files' => [UploadedFile::fake()->createWithContent('Resolution-No-005 - Copy.pdf', $bytes)], 'folder_id' => $folder->id,
    ])->assertOk()->assertJsonPath('copies.0.original', 'Resolution-No-005.pdf')
        ->assertJsonPath('copies.0.folder', 'Resolutions');

    $copy = File::where('filename', 'Resolution-No-005 - Copy.pdf')->first();
    expect($copy->duplicate_similarity)->toBe(100)
        ->and($copy->duplicateOf->filename)->toBe('Resolution-No-005.pdf');
});

test('a copy of a file in a restricted folder does not reveal that file', function () {
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);
    $owner = dupOfficer();
    $other = dupOfficer();
    $private = Folder::create(['name' => 'Disciplinary cases', 'is_restricted' => true, 'created_by' => $owner->id]);
    $open = Folder::create(['name' => 'General']);
    $bytes = "%PDF-1.4\n% complaint letter\n";

    $this->actingAs($owner)->postJson(route('files.store'), [
        'files' => [UploadedFile::fake()->createWithContent('complaint-juan.pdf', $bytes)], 'folder_id' => $private->id,
    ])->assertOk();

    $response = $this->actingAs($other)->postJson(route('files.store'), [
        'files' => [UploadedFile::fake()->createWithContent('letter.pdf', $bytes)], 'folder_id' => $open->id,
    ])->assertOk()->assertJsonPath('copies.0.original', null);

    expect($response->json('message'))->not->toContain('complaint-juan');

    $this->actingAs($other)->get(route('folders.show', $open->id))
        ->assertOk()
        ->assertSee('Possible copy')
        ->assertSee('a file in a folder you can’t open', false)
        ->assertDontSee('complaint-juan');
});

test('the label shows in the folder and "Not a copy, keep both" is kept', function () {
    $user = dupOfficer();
    $folder = Folder::create(['name' => 'Resolutions']);
    $older = dupFile(['folder_id' => $folder->id, 'filename' => 'Resolution-No-005.docx', 'ocr_text' => RESOLUTION_TEXT]);
    $newer = dupFile(['folder_id' => $folder->id, 'filename' => 'Resolution-No-006.docx', 'ocr_text' => RESOLUTION_TEXT . ' Approved.']);
    dupRead($older);
    dupRead($newer);

    $this->actingAs($user)->get(route('folders.show', $folder->id))
        ->assertOk()->assertSee('Possible copy')->assertSee('Not a copy, keep both');

    $this->actingAs($user)->post(route('file.notACopy', $newer->id))->assertSessionHas('success');

    expect($newer->fresh()->duplicate_dismissed_at)->not->toBeNull()
        ->and(dupRead($newer->fresh()))->toBeNull()
        ->and(File::flaggedAsCopy()->count())->toBe(0);

    $this->actingAs($user)->get(route('folders.show', $folder->id))
        ->assertOk()->assertDontSee('Possible copy')->assertDontSee('Not a copy, keep both');
});

test('a past school year\'s file can still be marked "not a copy" - it changes only the label', function () {
    $older = dupFile(['ocr_text' => RESOLUTION_TEXT, 'is_archived' => true]);
    $newer = dupFile(['filename' => 'resolution-006.docx', 'ocr_text' => RESOLUTION_TEXT, 'is_archived' => true]);
    dupRead($older);
    dupRead($newer);

    $this->actingAs(dupOfficer())->post(route('file.notACopy', $newer->id))->assertSessionHas('success');

    expect($newer->fresh()->duplicate_dismissed_at)->not->toBeNull();
});

test('a copy stops being flagged once its original is in Trash', function () {
    $older = dupFile(['ocr_text' => MINUTES_TEXT]);
    $newer = dupFile(['filename' => 'minutes - Copy.docx', 'ocr_text' => MINUTES_TEXT]);
    dupRead($older);
    dupRead($newer);

    expect(File::flaggedAsCopy()->pluck('id')->all())->toBe([$newer->id]);

    $older->moveToTrash();

    expect(File::flaggedAsCopy()->count())->toBe(0);
});

test('files saved before the check existed are fingerprinted and checked by the command', function () {
    Storage::disk('local')->put('uploads/1/a.pdf', '%PDF-1.4 same');
    Storage::disk('local')->put('uploads/1/b.pdf', '%PDF-1.4 same');
    $folder = Folder::create(['name' => 'Minutes']);
    $a = dupFile(['folder_id' => $folder->id, 'filename' => 'a.pdf', 'local_path' => 'uploads/1/a.pdf', 'storage_type' => 'local']);
    $b = dupFile(['folder_id' => $folder->id, 'filename' => 'b.pdf', 'local_path' => 'uploads/1/b.pdf', 'storage_type' => 'local']);
    $c = dupFile(['folder_id' => $folder->id, 'filename' => 'minutes.docx', 'ocr_text' => MINUTES_TEXT]);
    $d = dupFile(['folder_id' => $folder->id, 'filename' => 'minutes (1).docx', 'ocr_text' => MINUTES_TEXT]);

    $this->artisan('files:find-duplicates')->assertSuccessful();

    expect($a->fresh()->content_hash)->toBe(hash('sha256', '%PDF-1.4 same'))
        ->and($b->fresh()->duplicate_of_id)->toBe($a->id)
        ->and($c->fresh()->text_terms)->toBeArray()
        ->and($d->fresh()->duplicate_of_id)->toBe($c->id)
        ->and($a->fresh()->duplicate_of_id)->toBeNull();
});
