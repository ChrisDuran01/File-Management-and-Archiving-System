<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\ArchiveInsights;
use App\Services\DocumentKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function misUser(): User
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
function misOfficer(): User
{
    $user = misUser();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert([
        'user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active',
    ]);

    return $user;
}

function misFile(Folder $folder, string $name, string $text, array $attributes = []): File
{
    $file = new File();
    $file->forceFill(array_merge([
        'folder_id'    => $folder->id,
        'filename'     => $name,
        'filepath'     => 'f_' . uniqid() . '.docx',
        'size'         => 2048,
        'storage_type' => 'cloud',
        'ocr_text'     => $text,
        'content_hash' => hash('sha256', $text),
    ], $attributes))->save();

    return $file->fresh();
}

/** What the Reports page lists as maybe in the wrong folder. */
function misfiledFor(?User $viewer = null): array
{
    return app(ArchiveInsights::class)->build($viewer ?? misUser())['misfiled'];
}

function resoText(string $no, string $title, string $body): string
{
    return "OFFICE OF THE STUDENT GOVERNMENT\nSG Executive Resolution No. 07-16-26 (2627-{$no})\nSeries of 2026\n"
        . "A RESOLUTION {$title}\nAuthored by:\nTHE OFFICE OF THE STUDENT GOVERNMENT\n{$body}";
}

const MIS_FUND_BODY = 'WHEREAS, the withdrawal of funds from the DISADECO account requires approval; WHEREAS, the treasurer '
    . 'shall liquidate the amount with official receipts; NOW THEREFORE, BE IT RESOLVED to authorize the withdrawal '
    . 'and the purchase, subject to liquidation within thirty days. RESOLVED FURTHER that the auditor checks the receipts.';

const MIS_COUNCIL_BODY = 'WHEREAS, the councils of each college shall adopt a constitution and by-laws; WHEREAS, the article on '
    . 'elections provides that council officers are elected by the students of each college; WHEREAS, the section on '
    . 'membership defines the council chairperson, secretary and representatives; the constitution and by-laws of each '
    . 'council shall follow the article and section of the student government constitution, elections, officers, membership.';

const MIS_CBL_BODY = "Republic of the Philippines\nQUIRINO STATE UNIVERSITY\n2025 ADOPTED CONSTITUTION AND BY LAWS\nPREAMBLE\n"
    . 'ARTICLE I. The councils of each college follow this constitution and by-laws. ARTICLE II. Elections: council '
    . 'officers are elected by the students of each college. Section 1 membership; Section 2 chairperson, secretary and '
    . 'representatives; Section 3 the article on elections, officers, membership, constitution, by-laws, councils.';

const MIS_MINUTES_BODY = "Student Government Meeting\nDate: July 16, 2026\nVenue: SG Conference Hall\nI. Call to Order\n"
    . 'The meeting was called to order by the president. II. Attendance: the secretary checked attendance. III. Agenda: '
    . 'intramurals schedule, orientation program, polo shirts. The vice president moved to approve the agenda, seconded '
    . 'by the auditor. IV. Adjournment: the meeting was adjourned by the president at one in the afternoon.';

/**
 * The SG's folders as they are: resolutions, a report, the CBL, minutes.
 * "Reso 21" is about councils, so its words read like the CBL.
 *
 * @return array<string, mixed>
 */
function misArchive(): array
{
    $f = [
        'resolution' => Folder::create(['name' => 'Resolution']),
        'reports'    => Folder::create(['name' => 'Reports']),
        'cbl'        => Folder::create(['name' => 'QSU SG CBL']),
        'minutes'    => Folder::create(['name' => '(MOTM) Minutes of the Meetings']),
    ];
    misFile($f['resolution'], 'Reso-004.docx', resoText('004', 'AUTHORIZING THE WITHDRAWAL OF EIGHTEEN THOUSAND PESOS FOR ORIENTATION CASH PRIZES', MIS_FUND_BODY . ' Orientation cash prizes.'));
    misFile($f['resolution'], 'Reso-005.docx', resoText('005', 'AUTHORIZING THE WITHDRAWAL OF SEVEN THOUSAND PESOS FOR POLO SHIRTS', MIS_FUND_BODY . ' Polo shirts for officers.'));
    misFile($f['resolution'], 'Reso-007.docx', resoText('007', 'AUTHORIZING THE WITHDRAWAL OF THREE THOUSAND PESOS FOR THE OATH-TAKING', MIS_FUND_BODY . ' Oath-taking ceremony.'));
    $f['reso21'] = misFile($f['resolution'], 'SG Executive Reso 21 on Establishing of SCs.docx', resoText('021', 'ESTABLISHING THE STUDENT COUNCILS OF THE NEW COLLEGES', MIS_COUNCIL_BODY));
    misFile($f['reports'], 'Accomplishment-Report.docx', "ACCOMPLISHMENT REPORT\n3rd Quarter 2026\nThe student government held the orientation, "
        . "the oath-taking and the intramurals for the students.\nAPPROVED RESOLUTIONS\nA Resolution Directing All Student Councils To Elect "
        . "Their Officers\nA Resolution Withdrawing Funds For Coin Purses\nProjects done this quarter: orientation, oath-taking, intramurals, outreach.");
    misFile($f['cbl'], 'UPDATED-SG-CBL.docx', MIS_CBL_BODY);
    misFile($f['cbl'], 'SG-CBL-2024.docx', MIS_CBL_BODY . ' Amended in 2024.');
    misFile($f['minutes'], '3-MOTM-07-16-26.docx', "MINUTES OF THE MEETING\n" . MIS_MINUTES_BODY);
    misFile($f['minutes'], '6-MOTM-08-03-26.docx', "MINUTES OF THE MEETING\n" . str_replace('July 16', 'August 3', MIS_MINUTES_BODY));
    misFile($f['minutes'], '8-MOTM-08-18-26.docx', "MINUTES OF THE MEETING\n" . str_replace('July 16', 'August 18', MIS_MINUTES_BODY) . ' Budget report.');

    return $f;
}

test('the kind of document is read from its title, after the letterhead', function () {
    $kinds = app(DocumentKind::class);

    expect($kinds->fromText(resoText('004', 'AUTHORIZING A WITHDRAWAL', MIS_FUND_BODY)))->toBe('resolution')
        ->and($kinds->fromText("MINUTES OF THE MEETING\n" . MIS_MINUTES_BODY))->toBe('minutes')
        ->and($kinds->fromText("KATITIKAN NG PULONG\nIka-16 ng Hulyo"))->toBe('minutes')
        ->and($kinds->fromText(MIS_CBL_BODY))->toBe('cbl')
        ->and($kinds->fromText("GENERAL PLAN OF ACTION\nA.Y. 2026-2027"))->toBe('action_plan')
        // A report whose body lists "A Resolution ..." is still a report.
        ->and($kinds->fromText("ACCOMPLISHMENT REPORT\n3rd Quarter\nAPPROVED RESOLUTIONS\nA Resolution Directing All Councils"))->toBe('report')
        ->and($kinds->fromText(MIS_MINUTES_BODY))->toBeNull()
        ->and($kinds->fromText(''))->toBeNull();
});

test('a folder is for the kinds its name says, and an event folder is for none', function () {
    $kinds = app(DocumentKind::class);

    expect($kinds->forFolderName('(MOTM) Minutes of the Meetings'))->toBe(['minutes'])
        ->and($kinds->forFolderName('Resolution'))->toBe(['resolution'])
        ->and($kinds->forFolderName('QSU SG CBL'))->toBe(['cbl'])
        ->and($kinds->forFolderName('PR & PO'))->toBe(['purchase'])
        ->and($kinds->forFolderName('PR Materials'))->toBe([])
        ->and($kinds->forFolderName('SG Memories 2026'))->toBe([])
        ->and($kinds->forFolderName('Intramurals 2026'))->toBe([]);
});

test('a resolution uploaded into Reports is listed by its title, and its original is not blamed', function () {
    $f = misArchive();
    $copy = misFile($f['reports'], $f['reso21']->filename, $f['reso21']->ocr_text, ['duplicate_of_id' => $f['reso21']->id]);

    $misfiled = misfiledFor();

    expect($misfiled)->toHaveCount(1)
        ->and($misfiled[0]['file']['id'])->toBe($copy->id)
        ->and($misfiled[0]['reason'])->toBe('title')
        ->and($misfiled[0]['kind'])->toBe('Resolution')
        ->and($misfiled[0]['suggested']['id'])->toBe($f['resolution']->id);
});

test('a resolution whose words read like the CBL stays put, because its title says resolution', function () {
    $f = misArchive();

    expect(misfiledFor())->toBe([]);

    // The same words without the title are judged by words alone.
    $f['reso21']->forceFill(['ocr_text' => MIS_COUNCIL_BODY])->save();

    $misfiled = misfiledFor();
    expect($misfiled)->toHaveCount(1)
        ->and($misfiled[0]['reason'])->toBe('words')
        ->and($misfiled[0]['suggested']['id'])->toBe($f['cbl']->id);
});

test('a file without a title is still checked by its words', function () {
    $f = misArchive();
    $untitled = misFile($f['resolution'], 'meeting-notes.docx', MIS_MINUTES_BODY . ' Notes typed by the secretary.');

    $misfiled = misfiledFor();

    expect(array_column(array_column($misfiled, 'file'), 'id'))->toBe([$untitled->id])
        ->and($misfiled[0]['reason'])->toBe('words')
        ->and($misfiled[0]['suggested']['id'])->toBe($f['minutes']->id);
});

test('a file is not compared with its own copy in another folder', function () {
    $general = Folder::create(['name' => 'General']);
    $projects = Folder::create(['name' => 'Projects']);
    $budget = 'Canteen budget and liquidation: receipts for the canteen supplies, the cashier summary, the canteen '
        . 'treasurer remarks, unpaid canteen receipts, supplies inventory and the liquidation deadline for the canteen.';
    $file = misFile($general, 'canteen-budget.docx', $budget);
    misFile($general, 'outreach.docx', 'Outreach program in the barangay: volunteers, feeding program, school supplies '
        . 'for the pupils, transportation, coordination with the barangay captain and the photos of the outreach day.');
    misFile($general, 'sports.docx', 'Intramurals sports events: basketball, volleyball, chess and track; referees, '
        . 'brackets, venues, schedules, medals and certificates for the champions of every sports event this year.');
    $copy = misFile($projects, 'canteen-budget (1).docx', $budget . ' Copy.', ['content_hash' => 'not-the-same-bytes']);
    misFile($projects, 'canteen-project.docx', 'Canteen project proposal: the canteen supplies, canteen treasurer, '
        . 'receipts and the liquidation of the canteen budget, with the cashier summary and supplies inventory.');

    // Without knowing they are copies, the original reads like "Projects".
    expect(array_column(array_column(misfiledFor(), 'file'), 'id'))->toContain($file->id);

    $copy->forceFill(['duplicate_of_id' => $file->id])->save();

    expect(misfiledFor())->toBe([]);
});

test('a folder the viewer can not open is never suggested', function () {
    $owner = misOfficer();
    $officer = misOfficer();
    $resolutions = Folder::create(['name' => 'Resolution', 'is_restricted' => true, 'created_by' => $owner->id]);
    $reports = Folder::create(['name' => 'Reports']);
    misFile($resolutions, 'Reso-004.docx', resoText('004', 'AUTHORIZING A WITHDRAWAL', MIS_FUND_BODY));
    misFile($reports, 'Accomplishment-Report.docx', "ACCOMPLISHMENT REPORT\nThe orientation, the oath-taking, the intramurals, "
        . 'the outreach program, the feeding program and the sports events were held by the student government this quarter.');
    $misplaced = misFile($reports, 'Reso-005.docx', resoText('005', 'AUTHORIZING A WITHDRAWAL FOR POLO SHIRTS', MIS_FUND_BODY . ' Polo shirts.'));

    expect(misfiledFor($officer))->toBe([]);

    $misfiled = misfiledFor($owner);
    expect($misfiled[0]['file']['id'])->toBe($misplaced->id)
        ->and($misfiled[0]['suggested']['id'])->toBe($resolutions->id);
});
