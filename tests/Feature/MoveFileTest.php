<?php

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\ArchiveInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function mvSuperAdmin(): User
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
function mvOfficer(): User
{
    $user = mvSuperAdmin();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert([
        'user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active',
    ]);

    return $user;
}

function mvFile(Folder $folder, string $name, string $text = 'Some text.', array $attributes = []): File
{
    $file = new File();
    $file->forceFill(array_merge([
        'folder_id'    => $folder->id,
        'filename'     => $name,
        'filepath'     => 'f_' . uniqid() . '.docx',
        'size'         => 2048,
        'storage_type' => 'cloud',
        'ocr_text'     => $text,
    ], $attributes))->save();

    return $file->fresh();
}

const MV_RESO = "OFFICE OF THE STUDENT GOVERNMENT\nSG Executive Resolution No. 07-16-26 (2627-021)\nSeries of 2026\n"
    . "A RESOLUTION ESTABLISHING THE STUDENT COUNCILS OF THE NEW COLLEGES\nWHEREAS, the councils of each college shall elect officers.";

test('a file moves to another folder with its document record, and its Modified time stays', function () {
    $reports = Folder::create(['name' => 'Reports']);
    $resolutions = Folder::create(['name' => 'Resolution']);
    $file = mvFile($reports, 'Reso 21.docx', MV_RESO);
    File::whereKey($file->id)->update(['updated_at' => '2026-10-01 08:00:00']);
    $document = Document::create(['file_id' => $file->id, 'folder_id' => $reports->id, 'title' => 'Reso 21', 'date_received' => '2026-10-01']);

    $this->actingAs(mvOfficer())->post(route('files.move', $file->id), ['folder_id' => $resolutions->id])
        ->assertRedirect()
        ->assertSessionHas('success', '“Reso 21.docx” was moved to Resolution.');

    expect($file->fresh()->folder_id)->toBe($resolutions->id)
        ->and($file->fresh()->updated_at->format('Y-m-d H:i'))->toBe('2026-10-01 08:00')
        ->and($document->fresh()->folder_id)->toBe($resolutions->id)
        ->and(ActivityLog::where('activity', 'Moved file to another folder: Reso 21.docx (from "Reports" to "Resolution")')->exists())->toBeTrue();
});

test('a name already used in the new folder gets a number, as an upload would', function () {
    $from = Folder::create(['name' => 'Reports']);
    $to = Folder::create(['name' => 'Resolution']);
    mvFile($to, 'Reso 21.docx');
    $file = mvFile($from, 'Reso 21.docx');

    $this->actingAs(mvOfficer())->post(route('files.move', $file->id), ['folder_id' => $to->id])
        ->assertSessionHas('success', '“Reso 21 (1).docx” was moved to Resolution. It was renamed because that folder already has a file called “Reso 21.docx”.');

    expect($file->fresh()->filename)->toBe('Reso 21 (1).docx');
});

test('past school years neither give nor take files', function () {
    $current = Folder::create(['name' => 'Reports']);
    $past = Folder::create(['name' => 'Reports 2025', 'is_archived' => true, 'school_year' => '2025-2026']);
    $file = mvFile($current, 'Report.docx');
    $oldFile = mvFile($past, 'Old report.docx', 'Text.', ['is_archived' => true, 'school_year' => '2025-2026']);
    $officer = mvOfficer();

    $this->actingAs($officer)->post(route('files.move', $file->id), ['folder_id' => $past->id])
        ->assertSessionHas('error', 'Files can only be moved to this school year\'s folders.');
    $this->post(route('files.move', $oldFile->id), ['folder_id' => $current->id])
        ->assertSessionHas('error', 'This file is stored under a past school year and cannot be moved.');

    expect($file->fresh()->folder_id)->toBe($current->id)
        ->and($oldFile->fresh()->folder_id)->toBe($past->id);
});

test('only the folder creator or a SuperAdmin takes a file out of a restricted folder', function () {
    $owner = mvOfficer();
    $officer = mvOfficer();
    $restricted = Folder::create(['name' => 'Budget', 'is_restricted' => true, 'created_by' => $owner->id]);
    $restricted->allowedUsers()->attach($officer->id);
    $open = Folder::create(['name' => 'Reports']);
    $file = mvFile($restricted, 'Budget.xlsx');

    $this->actingAs($officer)->post(route('files.move', $file->id), ['folder_id' => $open->id])
        ->assertSessionHas('error', 'Only this folder\'s creator or a SuperAdmin can move files out of a restricted folder.');
    expect($file->fresh()->folder_id)->toBe($restricted->id);

    $this->actingAs($owner)->post(route('files.move', $file->id), ['folder_id' => $open->id])->assertSessionHas('success');
    expect($file->fresh()->folder_id)->toBe($open->id);
});

test('a folder the officer can not open is not a place to move to', function () {
    $hidden = Folder::create(['name' => 'Adviser only', 'is_restricted' => true, 'created_by' => mvOfficer()->id]);
    $file = mvFile(Folder::create(['name' => 'Reports']), 'Report.docx');

    $this->actingAs(mvOfficer())->post(route('files.move', $file->id), ['folder_id' => $hidden->id])
        ->assertSessionHas('error', 'That folder isn\'t available. Choose another one.');
    expect($file->fresh()->folder_id)->not->toBe($hidden->id);
});

test('a resolution in Reports says "Looks like a Resolution · Move", and Keep it here stops it there and in Reports', function () {
    $reports = Folder::create(['name' => 'Reports']);
    $resolutions = Folder::create(['name' => 'Resolution']);
    mvFile($resolutions, 'Reso 004.docx', "SG Executive Resolution No. 07-06-26 (2627-004)\nA RESOLUTION AUTHORIZING A WITHDRAWAL");
    mvFile($reports, 'Accomplishment Report.docx', "ACCOMPLISHMENT REPORT\n3rd Quarter 2026");
    $file = mvFile($reports, 'Reso 21.docx', MV_RESO);
    $officer = mvOfficer();

    $this->actingAs($officer)->get(route('folders.show', $reports->id))
        ->assertOk()
        ->assertSee('Looks like a Resolution · Move')
        ->assertSee('data-move-to="' . $resolutions->id . '"', false)
        ->assertSee('Move to another folder');

    $this->post(route('files.keepHere', $file->id))
        ->assertSessionHas('success', '“Reso 21.docx” stays in Reports. It won\'t be suggested for moving again.');

    expect($file->fresh()->folder_hint_dismissed_at)->not->toBeNull()
        ->and(ActivityLog::where('activity', 'Kept file in its folder: Reso 21.docx (Reports)')->exists())->toBeTrue()
        ->and(app(ArchiveInsights::class)->build($officer)['misfiled'])->toBe([]);

    $this->get(route('folders.show', $reports->id))->assertDontSee('Looks like a Resolution');
});

test('a folder named after an event may hold any kind, so nothing in it is flagged', function () {
    $event = Folder::create(['name' => 'Intramurals 2026']);
    Folder::create(['name' => 'Resolution']);
    mvFile($event, 'Reso 21.docx', MV_RESO);

    $this->actingAs(mvOfficer())->get(route('folders.show', $event->id))
        ->assertOk()
        ->assertDontSee('Looks like a');
});
