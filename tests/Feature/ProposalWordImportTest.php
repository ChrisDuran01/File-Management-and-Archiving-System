<?php

use App\Models\ActivityLog;
use App\Models\ActivityProposal;
use App\Models\User;
use App\Services\ProposalWordImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-27 10:00');
});

function importOfficer(): User
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

/** A Word paragraph; a list item when $list; "\n" becomes a line break. */
function wp(string $text, bool $list = false): string
{
    $num = $list ? '<w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>' : '';
    $runs = implode('<w:r><w:br/></w:r>', array_map(
        fn ($line) => '<w:r><w:t xml:space="preserve">'.htmlspecialchars($line, ENT_XML1).'</w:t></w:r>',
        explode("\n", $text)
    ));

    return '<w:p>'.$num.$runs.'</w:p>';
}

/** A table cell: one paragraph per line; 'span' columns wide; 'merge' => restart|continue (vertical merge). */
function wtc(string $text, array $opts = []): string
{
    $pr = (isset($opts['span']) ? '<w:gridSpan w:val="'.$opts['span'].'"/>' : '')
        .(isset($opts['merge']) ? ($opts['merge'] === 'restart' ? '<w:vMerge w:val="restart"/>' : '<w:vMerge/>') : '');

    return '<w:tc>'.($pr ? '<w:tcPr>'.$pr.'</w:tcPr>' : '').implode('', array_map(fn ($l) => wp($l), explode("\n", $text))).'</w:tc>';
}

function wtbl(array $rows): string
{
    return '<w:tbl>'.implode('', array_map(fn ($cells) => '<w:tr>'.implode('', $cells).'</w:tr>', $rows)).'</w:tbl>';
}

function docxUpload(string $body, string $name = 'FRESHIVAL 2026.docx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'dap');
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" '
        .'ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        .$body.'</w:body></w:document>');
    $zip->close();

    return new UploadedFile($path, $name, null, null, true);
}

/** Laid out like the SG's own proposal; one budget row's Total is wrong (13 x 60 is 780, not 870). */
function sampleProposalBody(): string
{
    return wp('DETAILED ACTIVITY PROPOSAL')
        .wtbl([
            [wtc('Title:'), wtc('FRESHIVAL 2026')],
            [wtc('Theme:'), wtc('Where New Beginnings Meet the Beat')],
            [wtc('Date:'), wtc('November 6–8, 2026')],
            [wtc('Proponent:'), wtc('Student Government')],
            [wtc('Budget:'), wtc('P 25,900.00')],
        ])
        .wp('I.  Rationale')
        .wp('First paragraph of the rationale.').wp('Second paragraph of the rationale.')
        .wp('II. Objectives')
        .wp('The celebration specifically aims to:')
        .wp('Welcome the first-year students', true).wp('Show their talents', true).wp('3. Build friendships across colleges')
        .wp('III. Methods & Approaches').wp('A program with a pageant and a concert.')
        .wp('IV. Schedule of Activities')
        .wtbl([
            [wtc('Time'), wtc('Activity'), wtc('Venue/Personnel')],
            [wtc('8:00 AM'), wtc("Registration\nAll first-year students"), wtc("Gymnasium\nSG Secretary")],
            [wtc('9:00 AM'), wtc('Opening Program'), wtc('SG President')],
            [wtc("HOST EXAMPLE NAME\nMaster of Ceremony", ['span' => 3])],
        ])
        .wp('V. Budgetary Requirements')
        .wp('Here are the following needs for this activity:')
        .wtbl([
            [wtc('Category'), wtc('Particular'), wtc('Quantity'), wtc('Unit'), wtc('Unit Price'), wtc('Total Price')],
            [wtc('Sash/award', ['merge' => 'restart']), wtc('Freshival Queen'), wtc('1'), wtc('pcs'), wtc('60'), wtc('60.00')],
            [wtc('', ['merge' => 'continue']), wtc('Freshival King'), wtc('1'), wtc('pcs'), wtc('60'), wtc('60.00')],
            [wtc('', ['merge' => 'continue']), wtc('Emerging Leader Award'), wtc('13'), wtc('pcs'), wtc('60'), wtc('870.00')],
            [wtc('Styling'), wtc('Stage and venue decoration'), wtc('1'), wtc('set'), wtc('25,000'), wtc('25,000.00')],
            [wtc('Grand Total', ['span' => 5]), wtc('25,990.00')],
        ])
        .wp('Prepared by:')
        .wtbl([[wtc("FIRST OFFICER\nSG Secretary"), wtc("SECOND OFFICER\nSG Vice President")]])
        .wp('Approved:')
        .wp('DR. EXAMPLE NAME').wp('University President');
}

it('reads every part of a Word proposal laid out like the SG\'s', function () {
    $result = app(ProposalWordImport::class)->read(docxUpload(sampleProposalBody())->getRealPath());
    $f = $result['fields'];

    expect($f['title'])->toBe('FRESHIVAL 2026')
        ->and($f['theme'])->toBe('Where New Beginnings Meet the Beat')
        ->and($f['starts_on'])->toBe('2026-11-06')
        ->and($f['ends_on'])->toBe('2026-11-08')
        ->and($f['proponent'])->toBe('Student Government')
        ->and($f['rationale'])->toBe("First paragraph of the rationale.\nSecond paragraph of the rationale.")
        ->and($f['objectives_intro'])->toBe('The celebration specifically aims to:')
        ->and($f['objectives'])->toBe(['Welcome the first-year students', 'Show their talents', 'Build friendships across colleges'])
        ->and($f['methods'])->toBe('A program with a pageant and a concert.')
        ->and($f['schedule'])->toBe([
            ['time' => '8:00 AM', 'activity' => "Registration\nAll first-year students", 'personnel' => "Gymnasium\nSG Secretary"],
            ['time' => '9:00 AM', 'activity' => 'Opening Program', 'personnel' => 'SG President'],
        ])
        ->and($f['master_of_ceremony'])->toBe('HOST EXAMPLE NAME')
        ->and($f['signatories']['prepared'])->toBe([
            ['name' => 'FIRST OFFICER', 'position' => 'SG Secretary'],
            ['name' => 'SECOND OFFICER', 'position' => 'SG Vice President'],
        ])
        ->and($f['signatories']['approved'])->toBe([['name' => 'DR. EXAMPLE NAME', 'position' => 'University President']]);

    // The merged Category goes down to every row it covers.
    expect(array_column($result['items'], 'category'))->toBe(['Sash/award', 'Sash/award', 'Sash/award', 'Styling'])
        ->and($result['items'][2])->toBe(['category' => 'Sash/award', 'particular' => 'Emerging Leader Award', 'quantity' => '13', 'unit' => 'pcs', 'unit_price' => '60'])
        ->and($result['items'][3]['unit_price'])->toBe('25000');

    // The row whose Total doesn't match, and the Grand Total of the file.
    expect(array_keys($result['checks']))->toBe([2])
        ->and($result['checks'][2])->toContain('₱870.00')->toContain('₱780.00')
        ->and(implode(' ', $result['warnings']))->toContain('₱25,990.00')->toContain('₱25,900.00');
});

it('fills in a new proposal from the Word file, saves nothing until the officer does, and logs where it came from', function () {
    $officer = importOfficer();

    $this->actingAs($officer)->get(route('proposals.import'))->assertOk()->assertSee('Import a proposal from Word');

    $this->post(route('proposals.import.read'), ['file' => docxUpload(sampleProposalBody())])
        ->assertRedirect(route('proposals.create'));
    expect(ActivityProposal::count())->toBe(0);

    $this->get(route('proposals.create'))->assertOk()
        ->assertSee('Filled in from “FRESHIVAL 2026.docx”', false)
        ->assertSee('value="FRESHIVAL 2026"', false)
        ->assertSee('value="2026-11-06"', false)
        ->assertSee('value="Build friendships across colleges"', false)
        ->assertSee('value="HOST EXAMPLE NAME"', false)
        ->assertSee('value="Emerging Leader Award"', false)
        ->assertSee('value="DR. EXAMPLE NAME"', false)
        ->assertSee('Check this row.')
        ->assertSee('name="imported_from" value="FRESHIVAL 2026.docx"', false);

    $this->post(route('proposals.store'), ['title' => 'FRESHIVAL 2026', 'imported_from' => 'FRESHIVAL 2026.docx'])->assertRedirect();
    expect(ActivityLog::where('activity', 'Created activity proposal "FRESHIVAL 2026" (imported from Word file "FRESHIVAL 2026.docx")')->exists())->toBeTrue();
});

it('turns away files that are not Word documents', function () {
    $officer = importOfficer();

    $this->actingAs($officer)->from(route('proposals.import'))
        ->post(route('proposals.import.read'), ['file' => UploadedFile::fake()->create('proposal.pdf', 10, 'application/pdf')])
        ->assertRedirect(route('proposals.import'))
        ->assertSessionHasErrors('file');

    $notAZip = UploadedFile::fake()->createWithContent('old proposal.docx', 'this is not a Word file');
    $this->from(route('proposals.import'))->post(route('proposals.import.read'), ['file' => $notAZip])
        ->assertSessionHasErrors(['file' => 'This file could not be opened as a Word (.docx) file.']);
});

it('reads the ways the SG writes dates', function () {
    $dates = fn ($text) => app(ProposalWordImport::class)->dates($text);

    expect($dates('October 22, 2026'))->toBe(['2026-10-22', null])
        ->and($dates('November 6–8, 2026'))->toBe(['2026-11-06', '2026-11-08'])
        ->and($dates('November 30 – December 2, 2026'))->toBe(['2026-11-30', '2026-12-02'])
        ->and($dates('Dec. 30, 2026 to Jan. 2, 2027'))->toBe(['2026-12-30', '2027-01-02'])
        ->and($dates('Sept 5, 2026'))->toBe(['2026-09-05', null])
        ->and($dates('To be announced'))->toBe([null, null]);
});

it('says what it could not find, and fills what it can', function () {
    $body = wp('Title: Tree Planting').wp('Date: Sometime in January')
        .wp('V. Budgetary Requirements')
        .wtbl([
            [wtc('Particular'), wtc('Qty'), wtc('Amount')],
            [wtc('Seedlings'), wtc('300 pcs'), wtc('4,500.00')],
        ]);

    $result = app(ProposalWordImport::class)->read(docxUpload($body)->getRealPath());

    expect($result['fields']['title'])->toBe('Tree Planting')
        ->and($result['fields']['starts_on'])->toBeNull()
        // "300 pcs" in the Quantity cell, and the Unit Price worked out from the Amount.
        ->and($result['items'])->toBe([['category' => '', 'particular' => 'Seedlings', 'quantity' => '300', 'unit' => 'pcs', 'unit_price' => '15']])
        ->and(implode(' ', $result['warnings']))
            ->toContain('The date "Sometime in January" could not be read')
            ->toContain('No I. Rationale was found')
            ->toContain('No II. Objectives were found');
});
