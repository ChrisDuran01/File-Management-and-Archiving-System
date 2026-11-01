<?php

use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Models\ScanBatch;
use App\Models\User;
use App\Services\CloudFileUploader;
use App\Services\PdfToolkit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // School year 2026-2027, so 2021-2022 to 2025-2026 take old records.
    Carbon::setTestNow('2026-10-25 10:00:00');
    Bus::fake();
    Storage::fake('local');
    Storage::fake('cloud');
});

function esyUser(): User
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
function esyOfficer(): User
{
    $user = esyUser();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert([
        'user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active',
    ]);

    return $user;
}

/** A folder stored under a past school year: made back then, or added later by $addedBy. */
function esyPastFolder(string $year, string $name = 'Resolution', ?User $addedBy = null): Folder
{
    $folder = new Folder();
    $folder->forceFill(['name' => $name, 'school_year' => $year, 'is_archived' => true]
        + ($addedBy ? ['added_later_at' => now(), 'added_later_by' => $addedBy->id] : []))->save();

    return $folder;
}

function esyFile(Folder $folder, array $attributes = []): File
{
    $file = new File();
    $file->forceFill(array_merge([
        'folder_id' => $folder->id, 'filename' => 'reso.pdf', 'filepath' => 'reso_'.uniqid().'.pdf', 'size' => 1024,
        'storage_type' => 'cloud', 'school_year' => $folder->school_year, 'is_archived' => $folder->is_archived,
    ], $attributes))->save();

    return $file->fresh();
}

function esyUpload($test, User $user, Folder $folder, array $extra = [])
{
    return $test->actingAs($user)->postJson(route('files.store'), [
        'files' => [UploadedFile::fake()->createWithContent('SG-Resolution-2526-034.pdf', "%PDF-1.4\n% old resolution\n")],
        'folder_id' => $folder->id,
    ] + $extra);
}

test('an officer can start one of the last five school years with a folder, and no other year', function () {
    $officer = esyOfficer();

    $this->actingAs($officer)->post(route('folders.store'), ['folder_name' => 'Resolution', 'school_year' => '2021-2022'])
        ->assertRedirect(route('folders.history', '2021-2022'));

    $folder = Folder::where('school_year', '2021-2022')->first();
    expect($folder->is_archived)->toBeTruthy()
        ->and($folder->added_later_by)->toBe($officer->id)
        ->and($folder->added_later_at)->not->toBeNull();

    foreach (['2020-2021', '2026-2027', '2027-2028', 'last year'] as $year) {
        $this->actingAs($officer)->post(route('folders.store'), ['folder_name' => 'Minutes', 'school_year' => $year])
            ->assertSessionHas('error');
    }
    expect(Folder::count())->toBe(1);
});

test('every school year can have its own "Resolution", and the current folders are their own group', function () {
    $officer = esyOfficer();
    esyPastFolder('2025-2026', 'Reports');                    // last year's, stored away

    $this->actingAs($officer)->post(route('folders.store'), ['folder_name' => 'Reports']);
    $this->actingAs($officer)->post(route('folders.store'), ['folder_name' => 'Reports']);
    $this->actingAs($officer)->post(route('folders.store'), ['folder_name' => 'Resolution', 'school_year' => '2024-2025']);
    $this->actingAs($officer)->post(route('folders.store'), ['folder_name' => 'Resolution', 'school_year' => '2023-2024']);
    $this->actingAs($officer)->post(route('folders.store'), ['folder_name' => 'Resolution', 'school_year' => '2023-2024']);

    expect(Folder::where('is_archived', false)->pluck('name')->sort()->values()->all())->toBe(['Reports', 'Reports (1)'])
        ->and(Folder::where('school_year', '2024-2025')->pluck('name')->all())->toBe(['Resolution'])
        ->and(Folder::where('school_year', '2023-2024')->pluck('name')->sort()->values()->all())->toBe(['Resolution', 'Resolution (1)']);
});

test('a file uploaded into a past year gets that year, is read-only for officers, and only the Super Admin can fix it', function () {
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);
    $officer = esyOfficer();
    $folder = esyPastFolder('2025-2026', 'Resolution', $officer);

    esyUpload($this, $officer, $folder, ['pending_approval' => 1])->assertOk();

    $file = File::where('folder_id', $folder->id)->first();
    expect($file->school_year)->toBe('2025-2026')
        ->and((bool) $file->is_archived)->toBeTrue()
        ->and($file->added_later_by)->toBe($officer->id)
        ->and($file->is_pending_approval)->toBeFalse();

    $this->actingAs($officer)->post(route('files.rename', $file->id), ['new_name' => 'oops'])->assertSessionHas('error');
    $this->actingAs($officer)->delete(route('files.destroy', $file->id))->assertSessionHas('error');
    expect($file->fresh()->deleted_at)->toBeNull();

    $admin = esyUser();                                       // no officer term = Super Admin
    $this->actingAs($admin)->post(route('file.toggleAccess', $file->id))->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('files.destroy', $file->id))->assertSessionHas('success');
    expect(File::find($file->id))->toBeNull();
});

test('records made during their own school year stay locked, even for the Super Admin', function () {
    $admin = esyUser();
    $folder = esyPastFolder('2025-2026');                     // stored away when that term ended
    $file = esyFile($folder);

    $this->actingAs($admin)->delete(route('files.destroy', $file->id))->assertSessionHas('error');
    $this->actingAs($admin)->put(route('folders.update', $folder->id), ['name' => 'Renamed'])->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('folders.destroy', $folder->id))->assertSessionHas('error');

    // An added-later folder that also holds one of that year's own records stays too.
    $addedLater = esyPastFolder('2025-2026', 'Minutes', $admin);
    esyFile($addedLater);
    $this->actingAs($admin)->delete(route('folders.destroy', $addedLater->id))->assertSessionHas('error');

    expect($file->fresh()->deleted_at)->toBeNull()
        ->and($folder->fresh()->name)->toBe('Resolution')
        ->and(Folder::count())->toBe(2);
});

test('the Super Admin can rename and trash a folder added later, landing on its year', function () {
    $admin = esyUser();
    $folder = esyPastFolder('2024-2025', 'Resolutoin', esyOfficer());

    $this->actingAs($admin)->put(route('folders.update', $folder->id), ['name' => 'Resolution'])->assertSessionHas('success');
    expect($folder->fresh()->name)->toBe('Resolution');

    $this->actingAs($admin)->delete(route('folders.destroy', $folder->id))
        ->assertRedirect(route('folders.history', '2024-2025'));
    expect(Folder::find($folder->id))->toBeNull();
});

test('a past year outside the five open years takes no more files', function () {
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);
    $folder = esyPastFolder('2019-2020');

    esyUpload($this, esyOfficer(), $folder)->assertStatus(422);
    expect(File::count())->toBe(0);
});

test('nobody uploads into a restricted folder they can not open', function () {
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);
    $owner = esyOfficer();
    $folder = Folder::create(['name' => 'Disciplinary cases', 'is_restricted' => true, 'created_by' => $owner->id]);

    esyUpload($this, esyOfficer(), $folder)->assertStatus(403);
    esyUpload($this, $owner, $folder)->assertOk();
    expect(File::count())->toBe(1);
});

test('a scanned hardcopy can be filed into a past year, getting that year', function () {
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);
    $this->mock(PdfToolkit::class)->shouldReceive('extractRange')->andReturnUsing(function () {
        $path = tempnam(sys_get_temp_dir(), 'seg');
        file_put_contents($path, "%PDF-1.4\n% one old resolution\n");

        return $path;
    });
    Storage::disk('local')->put('scans/old.pdf', "%PDF-1.4\n% scanned\n");
    $officer = esyOfficer();
    $folder = esyPastFolder('2025-2026', 'Resolution', $officer);
    $batch = ScanBatch::create([
        'original_filename' => 'old.pdf', 'stored_disk' => 'local', 'stored_path' => 'scans/old.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 20, 'checksum' => 'abc', 'status' => 'ready_for_review', 'page_count' => 1,
    ]);

    $this->actingAs($officer)->get(route('documents.scans.review', $batch))
        ->assertOk()->assertSee('Past school year 2025-2026')->assertSee('Resolution (SY 2025-2026)');

    $this->actingAs($officer)->postJson(route('documents.scans.file', $batch), [
        'target_folder_id' => $folder->id,
        'segments' => [['start_page' => 1, 'end_page' => 1, 'title' => 'Resolution 2526-034', 'date_received' => '2026-10-25']],
    ])->assertOk()->assertJsonPath('ok', true);

    $file = File::where('folder_id', $folder->id)->first();
    $document = Document::where('file_id', $file->id)->first();
    expect($file->school_year)->toBe('2025-2026')
        ->and((bool) $file->is_archived)->toBeTrue()
        ->and($file->added_later_by)->toBe($officer->id)
        ->and($document->school_year)->toBe('2025-2026')
        ->and($document->is_archived)->toBeTrue();
});

test('the pages offer adding old records only where it is allowed', function () {
    $officer = esyOfficer();
    $open = esyPastFolder('2025-2026', 'Resolution', $officer);
    esyFile($open, ['added_later_at' => now(), 'added_later_by' => $officer->id]);
    $closed = esyPastFolder('2019-2020', 'Minutes');

    $this->actingAs($officer)->get(route('folders.index'))
        ->assertOk()->assertSee('Add an earlier school year')->assertSee('2021-2022')->assertDontSee('<option value="2020-2021">', false);

    $this->actingAs($officer)->get(route('folders.history', '2025-2026'))
        ->assertOk()->assertSee('New folder')->assertSee('Added later');
    $this->actingAs($officer)->get(route('folders.history', '2019-2020'))
        ->assertOk()->assertDontSee('New folder');
    $this->actingAs($officer)->get(route('folders.history', 'not-a-year'))->assertNotFound();

    $this->actingAs($officer)->get(route('folders.show', $open->id))
        ->assertOk()->assertSee('Old records from that year can be added here')->assertSee('Added later by '.$officer->name)
        ->assertSee('Upload files')->assertDontSee('Move folder to Trash');
    $this->actingAs(esyUser())->get(route('folders.show', $open->id))->assertSee('Move folder to Trash');
    $this->actingAs($officer)->get(route('folders.show', $closed->id))
        ->assertOk()->assertSee('is read-only')->assertDontSee('id="fabMain"', false);
});
