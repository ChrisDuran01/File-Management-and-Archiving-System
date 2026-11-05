<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\OcrExtractor;
use App\Services\ProposalFileImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-27 10:00');
});

function fileImportOfficer(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert(['user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active']);

    return $user;
}

/**
 * pdftotext -layout of the sample proposal saved as PDF by Microsoft Word
 * (placeholder names): a two-page PDF whose tables have no borders in the
 * text, rows that wrap, the emcee's name above "Master of Ceremony", and
 * three signatories side by side.
 */
function proposalPdfLayout(): string
{
    return file_get_contents(base_path('tests/Fixtures/proposal-pdf-layout.txt'));
}

it('reads every part of a proposal from a PDF saved from Word', function () {
    $result = app(ProposalFileImport::class)->fromLayoutText(proposalPdfLayout(), 'PDF');
    $f = $result['fields'];

    expect($f['title'])->toBe('FRESHIVAL 2026')
        ->and($f['theme'])->toBe('Where New Beginnings Meet the Beat')
        ->and($f['starts_on'])->toBe('2026-10-22')
        ->and($f['proponent'])->toBe('Student Government')
        // Lines that wrapped are joined; a short line ending a sentence ends the paragraph.
        ->and($f['rationale'])->toBe("Every school year, the Student Government welcomes the new students with a festival of music, talent and friendship.\nThis year the festival also recognizes first-year students who show leadership.")
        ->and($f['objectives_intro'])->toBe('The celebration specifically aims to:')
        ->and($f['objectives'])->toBe(['Welcome the first-year students to the University;', 'Showcase the talents of the new students; and', 'Recognize emerging student leaders.'])
        ->and($f['methods'])->toBe('The program will be held at the University Gymnasium with a pageant, performances and an awarding.')
        ->and($f['schedule'])->toBe([
            ['time' => '1:00-1:30 pm', 'activity' => 'Registration', 'personnel' => "University Gymnasium\nSG Secretary"],
            ['time' => '1:30-2:00 pm', 'activity' => "Opening Program\nPrayer, National Anthem, Opening\nRemarks", 'personnel' => 'SG President'],
        ])
        // Printed above the words, as Word centres the spanning row.
        ->and($f['master_of_ceremony'])->toBe('HOST EXAMPLE NAME');

    // Each word under the header it sits closest to; wrapped particulars joined; the Category carried down.
    expect($result['items'])->toHaveCount(8)
        ->and($result['items'][1])->toBe(['category' => 'Sash/award', 'particular' => 'Freshival King', 'quantity' => '1', 'unit' => 'pcs', 'unit_price' => '60'])
        ->and($result['items'][3])->toBe(['category' => 'Styling', 'particular' => 'Stage and venue decoration', 'quantity' => '1', 'unit' => 'set', 'unit_price' => '25000'])
        ->and($result['items'][7])->toBe(['category' => 'Prizes', 'particular' => 'Cash prizes for winners', 'quantity' => '1', 'unit' => 'lot', 'unit_price' => '6800'])
        // Every row's Total and the Grand Total add up, so nothing is marked.
        ->and($result['checks'])->toBe([]);

    // Three people side by side under one label, each with the position under their name.
    expect($f['signatories']['prepared'])->toBe([
        ['name' => 'FIRST OFFICER', 'position' => 'SG Secretary'],
        ['name' => 'SECOND OFFICER', 'position' => 'SG Vice President'],
        ['name' => 'THIRD OFFICER', 'position' => 'Student Regent/SG President'],
    ])
        ->and($f['signatories']['noted'])->toBe([['name' => 'ADVISER EXAMPLE NAME', 'position' => 'SG Adviser']])
        ->and($f['signatories']['budget'])->toBe([['name' => 'BUDGET OFFICER NAME', 'position' => 'Budget Officer']])
        ->and($f['signatories']['approved'])->toBe([['name' => 'DR. EXAMPLE NAME', 'position' => 'University President']]);

    expect($result['warnings'])->toBe(['Check the Category of each budget row: a PDF doesn\'t show which rows a merged Category cell covers.']);
});

it('reads a scan the same way, and says to check it', function () {
    // As a scan misreads the part numbers: "Ul." for II., "Ml" for III.
    $scan = str_replace(['II.     Objectives', 'III.   Methods'], ['Ul.     Objectives', 'Ml     Methods'], proposalPdfLayout());

    $result = app(ProposalFileImport::class)->fromLayoutText($scan, 'scan');

    expect($result['found'])->toBe(['objectives' => 3, 'schedule' => 2, 'budget' => 8, 'signatories' => 6])
        ->and($result['warnings'][0])->toBe('Read from a scan: some letters and numbers may be misread. Check every part, the budget most of all.');
});

it('marks a budget row whose numbers do not add up, and leaves out what repeats on every page', function () {
    $layout = str_replace('13              pcs            60               780.00', '13              pcs            60               870.00', proposalPdfLayout());
    // A letterhead line printed at the top of both pages.
    $layout = implode("\f", array_map(fn ($page) => "        QUIRINO STATE UNIVERSITY\n".$page, explode("\f", $layout)));

    $result = app(ProposalFileImport::class)->fromLayoutText($layout, 'PDF');

    expect($result['checks'])->toBe([2 => 'The PDF says ₱870.00, but 13 × ₱60.00 = ₱780.00. Check this row.'])
        ->and($result['fields']['title'])->toBe('FRESHIVAL 2026')
        ->and($result['found']['signatories'])->toBe(6);
});

it('turns a crooked photo\'s words back into straight lines and columns', function () {
    // Tesseract TSV: two rows of a table, each word 2 px lower for every 100 px to the right.
    $word = fn ($line, $x, $y, $text) => implode("\t", [5, 1, 1, 1, $line, 1, $x, $y + intdiv($x * 2, 100), 10 * mb_strlen($text), 20, 95, $text]);
    $tsv = implode("\n", [
        "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext",
        $word(1, 100, 100, 'Particular'), $word(1, 800, 100, 'Quantity'), $word(1, 1500, 100, 'Total'),
        $word(2, 100, 130, 'Medals'), $word(2, 800, 130, '13'), $word(2, 1500, 130, '780.00'),
    ]);

    $lines = explode("\n", app(OcrExtractor::class)->layoutFromTsv($tsv));

    // Without straightening, "Total" (30 px lower) would land on the next line.
    expect($lines)->toHaveCount(2)
        ->and(preg_split('/\s{2,}/', trim($lines[0])))->toBe(['Particular', 'Quantity', 'Total'])
        ->and(preg_split('/\s{2,}/', trim($lines[1])))->toBe(['Medals', '13', '780.00'])
        ->and(strpos($lines[1], '13'))->toBe(strpos($lines[0], 'Quantity'));
});

it('opens a new proposal filled in from a PDF, and logs where it came from', function () {
    $officer = fileImportOfficer();
    $this->mock(ProposalFileImport::class, function ($mock) {
        $mock->shouldReceive('read')->once()->withArgs(fn ($path, $extension) => $extension === 'pdf')
            ->andReturn(app(ProposalFileImport::class)->fromLayoutText(proposalPdfLayout(), 'PDF'));
    });

    $this->actingAs($officer)
        ->post(route('proposals.import.read'), ['file' => UploadedFile::fake()->create('FRESHIVAL 2026.pdf', 40, 'application/pdf')])
        ->assertRedirect(route('proposals.create'));

    $this->get(route('proposals.create'))
        ->assertOk()
        ->assertSee('Filled in from “FRESHIVAL 2026.pdf”', false)
        ->assertSee('value="FRESHIVAL 2026"', false);

    $this->post(route('proposals.store'), ['title' => 'FRESHIVAL 2026', 'imported_from' => 'FRESHIVAL 2026.pdf'])->assertRedirect();
    expect(ActivityLog::where('activity', 'Created activity proposal "FRESHIVAL 2026" (imported from file "FRESHIVAL 2026.pdf")')->exists())->toBeTrue();
});

it('says when a file has no text it can read', function () {
    expect(fn () => app(ProposalFileImport::class)->fromLayoutText("\n  \f  \n", 'scan'))
        ->toThrow(RuntimeException::class, 'No text could be read from this scan.');
});
