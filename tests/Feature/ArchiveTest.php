<?php

use App\Models\Archive;
use App\Models\ArchiveFile;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\FolderArchiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function archiveSuperAdmin(): User
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
function archiveOfficer(): User
{
    $user = archiveSuperAdmin();

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

/** A file with real bytes on the (faked) local disk, so FolderArchiver can zip it. */
function archiveFile(Folder $folder, string $filename, array $attributes = []): File
{
    $localPath = 'uploads/' . $folder->id . '/' . uniqid() . '_' . $filename;
    Storage::disk('local')->put($localPath, 'contents of ' . $filename);

    $file = new File();
    $file->forceFill(array_merge([
        'folder_id'    => $folder->id,
        'filename'     => $filename,
        'filepath'     => uniqid() . '_' . $filename,
        'local_path'   => $localPath,
        'size'         => 100,
        'storage_type' => 'local',
    ], $attributes))->save();

    return $file->fresh();
}

function archiveFolder(Folder $folder): Archive
{
    // fresh(): the app always archives a folder it loaded from the database,
    // so column defaults (is_restricted) are filled in.
    $result = app(FolderArchiver::class)->archive($folder->fresh(), 'Tester');

    expect($result['success'])->toBeTrue();

    return Archive::latest('id')->first();
}

test('a file inside an archive is found by words that are only in its text', function () {
    $folder = Folder::create(['name' => 'Meeting Minutes', 'school_year' => '2023-2024']);
    archiveFile($folder, 'minutes-august.pdf', [
        'ocr_text' => 'The council approved the intramurals budget of twelve thousand pesos.',
    ]);
    archiveFile($folder, 'attendance.pdf', ['ocr_text' => 'Present: all officers.']);

    $archive = archiveFolder($folder);
    $admin   = archiveSuperAdmin();

    $this->actingAs($admin)->get(route('search', ['query' => 'intramurals']))
        ->assertOk()
        ->assertSee('Files in Archived Folders')
        ->assertSee(route('archives.previewFile', ['id' => $archive->id, 'filename' => 'minutes-august.pdf']), false)
        ->assertSee('Found in document text')
        ->assertSee('<mark>intramurals</mark>', false)
        ->assertDontSee('attendance');

    $this->actingAs($admin)->get(route('search.live', ['query' => 'intramurals']))
        ->assertOk()
        ->assertSee('minutes-august.pdf')
        ->assertSee('found in document text')
        ->assertDontSee('attendance');
});

test('a file inside an archive is found by its school year', function () {
    $folder = Folder::create(['name' => 'Resolutions', 'school_year' => '2023-2024']);
    archiveFile($folder, 'resolution.pdf');

    archiveFolder($folder);

    $this->actingAs(archiveSuperAdmin())->get(route('search', ['query' => '2023-2024']))
        ->assertOk()
        ->assertSee('resolution')
        ->assertSee('Matched school year');
});

test('a filed document inside an archive is still found by its details', function () {
    $folder = Folder::create(['name' => 'Incoming Letters']);
    $file   = archiveFile($folder, 'scan-0042.pdf');

    Document::create([
        'file_id'       => $file->id,
        'folder_id'     => $folder->id,
        'title'         => 'Request for venue',
        'reference_no'  => 'SG-2024-017',
        'sender'        => 'Office of Student Affairs',
        'date_received' => '2024-03-01',
    ]);

    archiveFolder($folder);

    // The document row itself does not survive archiving, so what was kept
    // with the archive is the only place its details can still be matched.
    expect(Document::withTrashed()->count())->toBe(0);

    $admin = archiveSuperAdmin();

    $this->actingAs($admin)->get(route('search', ['query' => 'SG-2024-017']))
        ->assertOk()
        ->assertSee('scan-0042')
        ->assertSee('Matched document details');

    $this->actingAs($admin)->get(route('search.live', ['query' => 'Student Affairs']))
        ->assertOk()
        ->assertSee('scan-0042.pdf')
        ->assertSee('matched document details');
});

test('files inside a restricted archive stay hidden from officers without access', function () {
    $creator = archiveOfficer();
    $other   = archiveOfficer();

    $folder = Folder::create(['name' => 'Finance', 'is_restricted' => true, 'created_by' => $creator->id]);
    archiveFile($folder, 'ledger.pdf', ['ocr_text' => 'Remaining balance after the outreach program.']);

    archiveFolder($folder);

    $this->actingAs($other)->get(route('search', ['query' => 'outreach']))
        ->assertOk()
        ->assertDontSee('ledger')
        ->assertDontSee('Remaining balance');

    $this->actingAs($other)->get(route('search.live', ['query' => 'outreach']))
        ->assertOk()
        ->assertDontSee('ledger');

    $this->actingAs($creator)->get(route('search', ['query' => 'outreach']))
        ->assertOk()
        ->assertSee('ledger');
});

test('restoring an archive hands the text back to the live file', function () {
    $folder = Folder::create(['name' => 'Meeting Minutes']);
    archiveFile($folder, 'minutes-august.pdf', [
        'ocr_text'   => 'The council approved the intramurals budget.',
        'ocr_status' => 'done',
    ]);

    $archive = archiveFolder($folder);
    $admin   = archiveSuperAdmin();

    $this->actingAs($admin)->post(route('archives.restore', $archive->id))
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'added back to main directory'));

    $restored = File::where('filename', 'minutes-august.pdf')->first();

    expect($restored->ocr_text)->toBe('The council approved the intramurals budget.')
        ->and($restored->ocr_status)->toBe('done');

    // Found once, as the live file - not a second time through the archive.
    $this->actingAs($admin)->get(route('search', ['query' => 'intramurals']))
        ->assertOk()
        ->assertSee('Found in document text')
        ->assertDontSee('Files in Archived Folders');
});

test('deleting an archive removes what was kept for search', function () {
    $folder = Folder::create(['name' => 'Meeting Minutes']);
    archiveFile($folder, 'minutes-august.pdf', ['ocr_text' => 'Some text.']);

    $archive = archiveFolder($folder);

    expect(ArchiveFile::count())->toBe(1);

    $this->actingAs(archiveSuperAdmin())->delete(route('archives.destroy', $archive->id))
        ->assertSessionHas('success');

    expect(ArchiveFile::count())->toBe(0);
});

test('the archive ZIP is kept where the web server cannot hand it out', function () {
    $folder = Folder::create(['name' => 'Finance']);
    archiveFile($folder, 'ledger.pdf');

    $archive = archiveFolder($folder);

    Storage::disk('local')->assertExists('archives/' . $archive->zip_name);
    Storage::disk('public')->assertMissing('archives/' . $archive->zip_name);

    // Still downloadable - through the app, which checks who is asking.
    $this->actingAs(archiveSuperAdmin())->get(route('archives.download', $archive->id))
        ->assertOk()
        ->assertDownload($archive->zip_name);

    $this->actingAs(archiveSuperAdmin())->delete(route('archives.destroy', $archive->id))
        ->assertSessionHas('success');

    Storage::disk('local')->assertMissing('archives/' . $archive->zip_name);
});

test('restoring puts the folder back under its school year with its owner and restriction', function () {
    $creator = archiveOfficer();
    $allowed = archiveOfficer();

    $folder = Folder::create([
        'name'             => 'Finance',
        'school_year'      => '2023-2024',
        'is_archived'      => true,
        'is_restricted'    => true,
        'created_by'       => $creator->id,
        'last_accessed_at' => now()->subYears(4),
    ]);
    $folder->allowedUsers()->sync([$allowed->id]);

    archiveFile($folder, 'ledger.pdf', [
        'school_year' => '2023-2024',
        'is_archived' => true,
        'is_public'   => 1,
        'created_at'  => '2023-09-15 08:00:00',
    ]);

    $archive = archiveFolder($folder);

    expect($archive->school_year)->toBe('2023-2024');

    $this->actingAs(archiveSuperAdmin())->post(route('archives.restore', $archive->id))
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'Previous School Years (2023-2024)'));

    $restored = Folder::find($folder->id);

    expect($restored)->not->toBeNull()
        ->and($restored->school_year)->toBe('2023-2024')
        ->and((bool) $restored->is_archived)->toBeTrue()
        ->and($restored->is_restricted)->toBeTrue()
        ->and($restored->created_by)->toBe($creator->id)
        ->and($restored->allowedUsers()->pluck('users.id')->all())->toBe([$allowed->id]);

    $file = File::where('folder_id', $restored->id)->first();

    expect($file->school_year)->toBe('2023-2024')
        ->and((bool) $file->is_archived)->toBeTrue()
        ->and((bool) $file->is_public)->toBeTrue()
        ->and($file->created_at->toDateString())->toBe('2023-09-15');

    // Unopened for four years before it was archived - restoring it must not
    // leave it for the retention run to sweep straight back into the archive.
    $this->artisan('folders:archive-stale')->assertSuccessful();

    expect(Folder::find($folder->id))->not->toBeNull()
        ->and(Archive::count())->toBe(1);
});

test('restoring brings a filed document record back', function () {
    $folder = Folder::create(['name' => 'Incoming Letters']);
    $file   = archiveFile($folder, 'scan-0042.pdf');

    $document = Document::create([
        'file_id'       => $file->id,
        'folder_id'     => $folder->id,
        'title'         => 'Request for venue',
        'document_type' => 'Letter',
        'reference_no'  => 'SG-2024-017',
        'sender'        => 'Office of Student Affairs',
        'document_date' => '2024-02-27',
        'date_received' => '2024-03-01',
        'page_count'    => 2,
    ]);

    $archive = archiveFolder($folder);

    expect(Document::withTrashed()->count())->toBe(0);

    $this->actingAs(archiveSuperAdmin())->post(route('archives.restore', $archive->id))
        ->assertSessionHas('success');

    $restored = Document::first();

    expect($restored)->not->toBeNull()
        ->and($restored->id)->toBe($document->id)
        ->and($restored->title)->toBe('Request for venue')
        ->and($restored->document_type)->toBe('Letter')
        ->and($restored->reference_no)->toBe('SG-2024-017')
        ->and($restored->sender)->toBe('Office of Student Affairs')
        ->and($restored->document_date->toDateString())->toBe('2024-02-27')
        ->and($restored->date_received->toDateString())->toBe('2024-03-01')
        ->and($restored->page_count)->toBe(2)
        ->and($restored->file->filename)->toBe('scan-0042.pdf')
        ->and($restored->folder->name)->toBe('Incoming Letters');

    // And it is an ordinary document again: on the Documents page, and found
    // as one by search rather than through the archive.
    $this->actingAs(archiveSuperAdmin())->get(route('search', ['query' => 'SG-2024-017']))
        ->assertOk()
        ->assertSee('Request for venue')
        ->assertDontSee('Files in Archived Folders');
});

test('an archive cannot be restored twice', function () {
    $folder = Folder::create(['name' => 'Meeting Minutes']);
    archiveFile($folder, 'minutes-august.pdf');

    $archive = archiveFolder($folder);
    $admin   = archiveSuperAdmin();

    $this->actingAs($admin)->post(route('archives.restore', $archive->id))->assertSessionHas('success');

    // Trashing the restored folder frees the "folder already exists" check,
    // so only the archive's own restored state is left to stop a second run.
    Folder::first()->moveToTrash();

    $this->actingAs($admin)->post(route('archives.restore', $archive->id))->assertSessionHas('error');

    expect(Folder::withTrashed()->count())->toBe(1);
});

test('an archive from before folder and file copies were kept still restores with its owner and restriction', function () {
    $creator = archiveOfficer();

    $folder = Folder::create(['name' => 'Old Minutes', 'is_restricted' => true, 'created_by' => $creator->id]);
    archiveFile($folder, 'minutes-2025.pdf');

    $archive = archiveFolder($folder);
    $archive->update(['folder_record' => null, 'school_year' => null]);
    $archive->files()->delete();

    $this->actingAs(archiveSuperAdmin())->post(route('archives.restore', $archive->id))
        ->assertSessionHas('success');

    $restored = Folder::first();

    expect($restored->name)->toBe('Old Minutes')
        ->and($restored->is_restricted)->toBeTrue()
        ->and($restored->created_by)->toBe($creator->id)
        ->and(File::where('folder_id', $restored->id)->pluck('filename')->all())->toBe(['minutes-2025.pdf']);
});
