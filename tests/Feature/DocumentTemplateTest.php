<?php

use App\Jobs\ExtractFileOcrText;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Letterhead;
use App\Models\Template;
use App\Models\User;
use App\Support\Documents\ResolutionDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // School year 2026-2027 (it starts in August).
    $this->travelTo('2026-10-22 10:00');
    Storage::fake('local');
    Storage::fake('cloud');
    Storage::fake('public');
    Queue::fake();
});

/** No officer_terms row counts as SuperAdmin - see Folder::isSuperAdmin(). */
function templateSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An ordinary officer: an active term in a position that isn't Super Admin. */
function templateOfficer(): User
{
    $user = templateSuperAdmin();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

function resolutionTemplate(): Template
{
    return Template::where('category', 'resolution')->firstOrFail();
}

/**
 * A folder anyone may open. files.folder_id is NOT NULL in the migrations
 * (only the live table allows root files), so every test file needs one.
 */
function templateFolderId(): int
{
    return DB::table('folders')->where('name', 'Records')->value('id')
        ?? DB::table('folders')->insertGetId(['name' => 'Records', 'created_at' => now(), 'updated_at' => now()]);
}

/** The request a filled-in resolution form sends. */
function resolutionRequest(array $fields = [], array $extra = []): array
{
    return $extra + [
        'folder_id' => templateFolderId(),
        'template_id' => resolutionTemplate()->id,
        'fields' => $fields + [
            'kind'       => 'SG Executive Resolution',
            'number'     => '10-22-26 (2627-001)',
            'title'      => 'A Resolution Requesting Water Stations',
            'author'     => 'The Office of the Student Government',
            'whereas'    => ['WHEREAS, students walk between buildings in the heat;', '', 'the **clinic** saw more cases;'],
            'moved_by'   => 'Juan Dela Cruz, SG Senator',
            'seconded'   => 'unanimously seconded by the officers',
            'resolved'   => ['as it is hereby resolved, that water stations be set up;', 'RESOLVED FURTHER, that the SG pays for the first month.'],
            'adopted_on' => '2026-10-22',
            'venue'      => 'the SG Conference Hall',
            'vote'       => 'Approved unanimously',
            'signatories' => [
                'certified' => [['name' => 'MARIA SANTOS', 'position' => "SG Secretary\nChief Legislator"], ['name' => '', 'position' => '']],
                'approved'  => [['name' => 'ENGR. ALFONSO R. SIMON, PhD', 'position' => 'University President']],
            ],
        ],
    ];
}

it('offers the resolution and the request letter, not the hidden memorandum', function () {
    $this->actingAs(templateSuperAdmin())
        ->get(route('templates.index'))
        ->assertOk()
        ->assertSee('Request letter')
        ->assertSee('Add WHEREAS')
        ->assertSee('10-22-26 (2627-001)')   // suggested number for today
        ->assertDontSee('data-template-id="'.Template::where('category', 'memorandum')->value('id').'"', false);
});

it('saves a resolution as a PDF with what was typed', function () {
    $user = templateSuperAdmin();

    $this->actingAs($user)
        ->post(route('templates.generate'), resolutionRequest())
        ->assertRedirect(route('templates.index'))
        ->assertSessionHas('success');

    $file = File::sole();
    expect($file->filename)->toBe('SG Executive Resolution No. 10-22-26 (2627-001).pdf')
        ->and($file->generated_from_template_id)->toBe(resolutionTemplate()->id)
        ->and($file->template_fields['whereas'])->toBe(['students walk between buildings in the heat;', 'the **clinic** saw more cases;'])
        ->and($file->template_fields['resolved'][1])->toBe('that the SG pays for the first month.')
        ->and($file->template_fields['signatories']['certified'])->toHaveCount(1)
        ->and($file->content_hash)->toBe(hash('sha256', Storage::disk('local')->get($file->local_path)));

    Storage::disk('cloud')->assertExists($file->filepath);
    Queue::assertPushed(ExtractFileOcrText::class);
    expect(ActivityLog::where('activity', 'like', 'Generated document from template%')->exists())->toBeTrue();
});

it('draws the resolution the way the SG lays it out', function () {
    $html = $this->actingAs(templateSuperAdmin())
        ->postJson(route('templates.preview'), resolutionRequest())
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('SG Executive Resolution No. 10-22-26 (2627-001)')
        ->toContain('Series of 2026')
        ->toContain('A RESOLUTION REQUESTING WATER STATIONS')
        ->toContain('<b>WHEREAS,</b> students walk')
        ->toContain('the <b>clinic</b> saw')
        ->toContain('<b>WHEREFORE</b>, upon the motion of Juan Dela Cruz, SG Senator and unanimously seconded by the officers, be it;')
        ->toContain('<b>RESOLVED,</b> as it is hereby resolved')
        ->toContain('<b>RESOLVED FURTHER,</b> that the SG pays')
        ->toContain('<b>ADOPTED</b> this 22<sup>nd</sup> day of October 2026 at the SG Conference Hall.')
        ->toContain('Approved unanimously on the 22<sup>nd</sup> day of October 2026.')
        ->toContain('Certified true and correct:')
        ->toContain('Chief Legislator')
        ->not->toContain('WHEREAS, WHEREAS');
});

it('keeps typed text from becoming markup', function () {
    $html = $this->actingAs(templateSuperAdmin())
        ->postJson(route('templates.preview'), resolutionRequest(['whereas' => ['<script>alert(1)</script>']]))
        ->json('html');

    expect($html)->not->toContain('<script>')->toContain('&lt;script&gt;');
});

it('suggests the next running number of the school year', function () {
    $user = templateSuperAdmin();
    $this->actingAs($user)->post(route('templates.generate'), resolutionRequest(['number' => '10-01-26 (2627-007)']));

    expect(ResolutionDocument::sequences(['04-06-26 (2526-034)', '10-01-26 (2627-007)', 'no number']))
        ->toBe(['2526' => 34, '2627' => 7])
        ->and(ResolutionDocument::suggestNumber('2026-04-06', ['2526' => 33]))->toBe('04-06-26 (2526-034)');

    $this->actingAs($user)->get(route('templates.index'))
        ->assertSee('10-22-26 (2627-008)')
        ->assertSee('Last one made here: <b>10-01-26 (2627-007)</b>', false);
});

it('starts a new resolution with the last one\'s signatories, venue and vote', function () {
    $user = templateSuperAdmin();
    $this->actingAs($user)->post(route('templates.generate'), resolutionRequest());

    $this->actingAs($user)->get(route('templates.index'))
        ->assertSee('value="MARIA SANTOS"', false)
        ->assertSee('value="the SG Conference Hall"', false)
        ->assertDontSee('students walk between buildings');
});

it('fixes a saved resolution in place, keeping the PDF before the fix as version 1', function () {
    $user = templateSuperAdmin();
    $this->actingAs($user)->post(route('templates.generate'), resolutionRequest());
    $file = File::sole();
    $oldLocal = $file->local_path;
    $oldRemote = $file->filepath;

    $this->actingAs($user)->get(route('templates.index', ['edit' => $file->id]))
        ->assertOk()
        ->assertSee('name="replace_file_id" value="'.$file->id.'"', false)
        ->assertSee('students walk between buildings in the heat;');

    $this->actingAs($user)
        ->post(route('templates.generate'), resolutionRequest(['title' => 'A Resolution Requesting More Water Stations'], ['replace_file_id' => $file->id]))
        ->assertRedirect(route('templates.index'));

    $file->refresh();
    expect(File::count())->toBe(1)
        ->and($file->template_fields['title'])->toBe('A Resolution Requesting More Water Stations')
        ->and($file->filename)->toBe('SG Executive Resolution No. 10-22-26 (2627-001).pdf')
        ->and($file->ocr_status)->toBe('pending');

    // The PDF that was sent before the fix stays, as version 1 (FileVersions).
    Storage::disk('local')->assertExists($oldLocal);
    Storage::disk('local')->assertExists($file->local_path);
    Storage::disk('cloud')->assertExists($oldRemote);
    expect($file->version_no)->toBe(2)
        ->and($file->version_note)->toBe('Edited with the form')
        ->and($file->versions()->sole()->only(['version_no', 'local_path', 'filepath']))
        ->toBe(['version_no' => 1, 'local_path' => $oldLocal, 'filepath' => $oldRemote])
        ->and(ActivityLog::where('activity', 'like', 'Edited document%')->exists())->toBeTrue();
});

it('copies a saved resolution into a new one', function () {
    $user = templateSuperAdmin();
    $this->actingAs($user)->post(route('templates.generate'), resolutionRequest());
    $file = File::sole();

    $this->actingAs($user)->get(route('templates.index', ['copy' => $file->id]))
        ->assertOk()
        ->assertDontSee('name="replace_file_id"', false)
        ->assertSee('students walk between buildings in the heat;')
        ->assertSee('10-22-26 (2627-002)');   // a new number, not the copied one

    $this->actingAs($user)->post(route('templates.generate'), resolutionRequest());
    expect(File::count())->toBe(2)
        ->and(File::latest('id')->value('filename'))->toBe('SG Executive Resolution No. 10-22-26 (2627-001) (2).pdf');
});

it('lets only those who manage the folder edit, and nobody edit a past school year', function () {
    $owner = templateSuperAdmin();
    $folderId = DB::table('folders')->insertGetId(['name' => 'Resolutions', 'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($owner)->post(route('templates.generate'), resolutionRequest([], ['folder_id' => $folderId]));
    $file = File::sole();

    $this->actingAs(templateOfficer())->get(route('templates.index', ['edit' => $file->id]))->assertForbidden();
    $this->actingAs(templateOfficer())->get(route('templates.index', ['copy' => $file->id]))->assertOk();

    $file->forceFill(['is_archived' => true])->save();
    $this->actingAs($owner)->get(route('templates.index', ['edit' => $file->id]))->assertForbidden();
});

it('needs a title, and refuses the hidden memorandum', function () {
    $user = templateSuperAdmin();

    $this->actingAs($user)->post(route('templates.generate'), resolutionRequest(['title' => '']))
        ->assertSessionHasErrors('fields.title');

    $this->actingAs($user)->post(route('templates.generate'), [
        'template_id' => Template::where('category', 'memorandum')->value('id'),
        'fields' => ['subject' => 'x'],
    ])->assertNotFound();

    expect(File::count())->toBe(0);
});

it('saves a request letter', function () {
    $user = templateSuperAdmin();
    $template = Template::where('category', 'request_letter')->firstOrFail();

    $this->actingAs($user)->post(route('templates.generate'), [
        'folder_id' => templateFolderId(),
        'template_id' => $template->id,
        'fields' => [
            'date' => '2026-10-22',
            'recipient_name' => 'ENGR. ALFONSO R. SIMON, PhD',
            'recipient_details' => "OIC-University President\nQuirino State University",
            'thru' => [['name' => 'JENALYN M. SARMIENTO, PhD', 'position' => 'Director for Student Affairs and Services']],
            'subject' => 'Use of the gym for Intramurals',
            'salutation' => 'Dear Sir:',
            'body' => "Greetings!\nWe ask to use the gym on November 14.",
            'closing' => 'Respectfully yours,',
            'signatories' => ['sender' => [['name' => 'JUAN DELA CRUZ', 'position' => 'President, Student Government']]],
        ],
    ])->assertRedirect(route('templates.index'));

    $file = File::sole();
    expect($file->filename)->toBe('Request letter - Use of the gym for Intramurals.pdf');

    $html = view('Admin.templates.documents.request-letter', ['fields' => \App\Support\Documents\RequestLetterDocument::normalize($file->template_fields)])->render();
    expect($html)->toContain('October 22, 2026')
        ->toContain('Thru:')
        ->toContain('<p class="letter-par">We ask to use the gym on November 14.</p>')
        ->toContain('JUAN DELA CRUZ');
});

it('still shows a letterhead around the sample resolution', function () {
    $letterhead = Letterhead::create(['name' => 'Plain', 'university_name' => 'QUIRINO STATE UNIVERSITY']);

    $this->actingAs(templateSuperAdmin())
        ->get(route('letterheads.preview', $letterhead))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
