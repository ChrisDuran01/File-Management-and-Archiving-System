<?php

use App\Models\ActivityLog;
use App\Models\ActivityProposal;
use App\Models\ActivityProposalItem;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\CloudFileUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // School year 2026-2027 (it starts in August).
    $this->travelTo('2026-10-22 10:00');
});

/** No officer_terms row counts as SuperAdmin - see Folder::isSuperAdmin(). */
function proposalSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An ordinary officer: an active 2026-2027 term in a position that isn't Super Admin. */
function proposalOfficer(string $position = 'Vice President'): User
{
    $user = proposalSuperAdmin();
    $positionId = DB::table('positions')->insertGetId(['position_name' => $position, 'is_super_admin' => false]);
    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

/** A saved proposal by $user with two budget rows: ₱3,600 + ₱1,200. */
function proposalFor(User $user, array $attributes = [], ?array $items = null): ActivityProposal
{
    $proposal = ActivityProposal::create($attributes + [
        'title'       => 'Leadership Training',
        'starts_on'   => '2026-11-14',
        'school_year' => '2026-2027',
        'objectives'  => ['Train officers to plan an activity'],
        'schedule'    => [['time' => '8:00 AM', 'activity' => 'Registration', 'personnel' => 'SG Secretary']],
        'created_by'  => $user->id,
    ]);

    $items ??= [
        ['category' => 'Meals', 'particular' => 'Lunch', 'quantity' => 60, 'unit' => 'pax', 'unit_price' => 60],
        ['category' => 'Supplies', 'particular' => 'Certificates', 'quantity' => 60, 'unit' => 'pcs', 'unit_price' => 20],
    ];
    foreach ($items as $i => $item) {
        $proposal->items()->create($item + ['position' => $i]);
    }

    return $proposal->fresh('items');
}

it('saves a proposal with its budget, leaving out the empty rows', function () {
    $officer = proposalOfficer();

    $this->actingAs($officer)->post('/proposals', [
        'title'            => 'Leadership Training',
        'theme'            => 'Lead with purpose',
        'starts_on'        => '2026-11-14',
        'proponent'        => 'Student Government',
        'rationale'        => 'Officers need training.',
        'objectives_intro' => 'The training aims to:',
        'objectives'       => ['Train officers', '', 'Draft a proposal'],
        'schedule'         => [
            ['time' => '8:00 AM', 'activity' => 'Registration', 'personnel' => "Lobby\nSG Secretary"],
            ['time' => '', 'activity' => '', 'personnel' => ''],
        ],
        'master_of_ceremony' => 'A. Host',
        'items' => [
            ['category' => 'Meals', 'particular' => 'Lunch', 'quantity' => '60', 'unit' => 'pax', 'unit_price' => '60'],
            ['category' => '', 'particular' => '', 'quantity' => '', 'unit' => '', 'unit_price' => ''],
            ['category' => 'Supplies', 'particular' => 'Certificates', 'quantity' => '60', 'unit' => 'pcs', 'unit_price' => '20.50'],
        ],
        'signatories' => [
            'prepared' => [['name' => 'A. Officer', 'position' => 'SG Secretary'], ['name' => '', 'position' => '']],
            'made_up'  => [['name' => 'Someone', 'position' => 'Not a group of the format']],
        ],
    ])->assertRedirect();

    $proposal = ActivityProposal::sole();

    expect($proposal->objectives)->toBe(['Train officers', 'Draft a proposal'])
        ->and($proposal->theme)->toBe('Lead with purpose')
        ->and($proposal->objectives_intro)->toBe('The training aims to:')
        ->and($proposal->schedule)->toBe([['time' => '8:00 AM', 'activity' => 'Registration', 'personnel' => "Lobby\nSG Secretary"]])
        ->and($proposal->signatories['prepared'])->toBe([['name' => 'A. Officer', 'position' => 'SG Secretary']])
        ->and($proposal->signatories)->not->toHaveKey('made_up')
        ->and($proposal->items)->toHaveCount(2)
        ->and($proposal->budgetTotal())->toBe(4830.0)
        ->and($proposal->school_year)->toBe('2026-2027')
        ->and($proposal->prepared_by_position)->toBe('Vice President')
        ->and((int) $proposal->created_by)->toBe($officer->id)
        ->and(ActivityLog::where('activity', 'Created activity proposal "Leadership Training"')->exists())->toBeTrue();

    // Total Price per row (60 x 20.50) and the whole budget.
    $this->get(route('proposals.show', $proposal))
        ->assertOk()
        ->assertSee('₱1,230.00')
        ->assertSee('₱4,830.00')
        ->assertSee('Total per category');
});

it('starts a new proposal with the signatories of the last one', function () {
    $officer = proposalOfficer();

    // Nothing to go on yet: the positions from the SG's format, names blank.
    $this->actingAs($officer)->get('/proposals/create')
        ->assertOk()
        ->assertSee('value="Budget Officer"', false)
        ->assertSee('value="Student Government"', false);

    proposalFor($officer, ['signatories' => [
        'approved' => [['name' => 'DR. EXAMPLE NAME', 'position' => 'University President']],
    ]]);

    $this->get('/proposals/create')
        ->assertSee('value="DR. EXAMPLE NAME"', false)
        ->assertDontSee('value="Budget Officer"', false);
});

it('prints the proposal as a PDF', function () {
    $proposal = proposalFor(proposalOfficer());

    $response = $this->actingAs(proposalOfficer('Secretary'))->get(route('proposals.pdf', $proposal));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');

    $this->get(route('proposals.pdf', [$proposal, 'letterhead' => 999]))->assertSessionHasErrors('letterhead');
});

it('lays out the PDF like the SG format', function () {
    $proposal = proposalFor(proposalOfficer(), [
        'theme'       => 'Where New Beginnings Meet the Beat',
        'starts_on'   => '2026-10-22',
        'proponent'   => 'Student Government',
        'signatories' => [
            'prepared' => [
                ['name' => 'FIRST OFFICER', 'position' => 'SG Secretary'],
                ['name' => 'SECOND OFFICER', 'position' => 'SG Vice President'],
                ['name' => 'THIRD OFFICER', 'position' => 'Student Regent/SG President'],
            ],
        ],
    ], [
        ['category' => 'Sash/award', 'particular' => 'Freshival Queen', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 60],
        ['category' => 'Sash/award', 'particular' => 'Freshival King', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 60],
        ['category' => 'Sash/award', 'particular' => 'Emerging Leader Award', 'quantity' => 13, 'unit' => 'pcs', 'unit_price' => 60],
        ['category' => 'Styling', 'particular' => 'Stage and venue decoration', 'quantity' => 1, 'unit' => 'set', 'unit_price' => 25000],
    ]);

    $html = view('Admin.proposals.pdf', ['proposal' => $proposal, 'letterhead' => null, 'borderBox' => null])->render();

    expect($html)
        ->toContain('DETAILED ACTIVITY PROPOSAL')
        ->toContain('October 22, 2026')
        ->toContain('P 25,900.00')           // Budget = the Grand Total
        ->toContain('780.00')                // 13 x 60
        ->toContain('>25000<')               // Unit Price as the SG writes it
        ->and(substr_count($html, '>Sash/award<'))->toBe(1)  // one shared Category cell
        ->and($html)->toContain('Prepared by:')
        ->toContain('THIRD OFFICER')
        ->not->toContain('Budget Available:');  // empty groups are left out
});

it('writes dates the way the SG does', function () {
    $label = fn ($start, $end = null) => (new ActivityProposal(['starts_on' => $start, 'ends_on' => $end]))->dateLabel();

    expect($label('2026-10-22'))->toBe('October 22, 2026')
        ->and($label('2026-11-06', '2026-11-08'))->toBe('November 6–8, 2026')
        ->and($label('2026-11-30', '2026-12-02'))->toBe('November 30 – December 2, 2026')
        ->and($label(null))->toBe('No date yet');
});

it('needs a particular, a quantity and a unit price on a budget row that has anything in it', function () {
    $this->actingAs(proposalOfficer())
        ->from('/proposals/create')
        ->post('/proposals', [
            'title' => 'Seminar',
            'items' => [
                5 => ['category' => 'Meals', 'particular' => '', 'quantity' => '10', 'unit' => '', 'unit_price' => ''],
            ],
        ])
        ->assertRedirect('/proposals/create')
        ->assertSessionHasErrors(['items.5.particular', 'items.5.unit_price'])
        ->assertSessionDoesntHaveErrors(['items.5.quantity']);

    expect(ActivityProposal::count())->toBe(0);
});

it('lets only the maker or the Super Admin change a proposal', function () {
    $maker = proposalOfficer('Vice President');
    $other = proposalOfficer('Secretary');
    $proposal = proposalFor($maker);

    $this->actingAs($other)->get(route('proposals.show', $proposal))->assertOk();
    $this->actingAs($other)->get(route('proposals.edit', $proposal))->assertForbidden();
    $this->actingAs($other)->put(route('proposals.update', $proposal), ['title' => 'Changed'])->assertForbidden();
    $this->actingAs($other)->delete(route('proposals.destroy', $proposal))->assertForbidden();
    expect($proposal->fresh()->title)->toBe('Leadership Training');

    $this->actingAs(proposalSuperAdmin())->put(route('proposals.update', $proposal), ['title' => 'Changed'])
        ->assertRedirect(route('proposals.show', $proposal));
    expect($proposal->fresh()->title)->toBe('Changed');
});

it('copies a proposal into a new one of your own, without the old dates', function () {
    $maker = proposalOfficer('Vice President');
    $copier = proposalOfficer('Secretary');
    $old = proposalFor($maker, [
        'school_year' => '2025-2026',
        'starts_on'   => '2025-11-15',
        'theme'       => 'Old theme',
        'signatories' => ['budget' => [['name' => 'BUDGET OFFICER NAME', 'position' => 'Budget Officer']]],
    ]);

    $response = $this->actingAs($copier)->post(route('proposals.copy', $old));

    $copy = ActivityProposal::where('copied_from_id', $old->id)->sole();
    $response->assertRedirect(route('proposals.edit', $copy));

    expect((int) $copy->created_by)->toBe($copier->id)
        ->and($copy->starts_on)->toBeNull()
        ->and($copy->school_year)->toBe('2026-2027')
        ->and($copy->prepared_by_position)->toBe('Secretary')
        ->and($copy->objectives)->toBe($old->objectives)
        ->and($copy->schedule)->toBe($old->schedule)
        ->and($copy->theme)->toBe('Old theme')
        ->and($copy->signatories)->toBe($old->signatories)
        ->and($copy->items)->toHaveCount(2)
        ->and($copy->budgetTotal())->toBe($old->budgetTotal());

    // The copy is the copier's to change; the original is untouched.
    $this->actingAs($copier)->get(route('proposals.edit', $copy))->assertOk();
    expect($old->fresh('items')->items)->toHaveCount(2);
});

it('replaces the budget and moves the proposal to the school year of its new date', function () {
    $maker = proposalOfficer();
    $proposal = proposalFor($maker);

    $this->actingAs($maker)->put(route('proposals.update', $proposal), [
        'title'     => 'Leadership Training',
        'starts_on' => '2027-09-10',
        'items'     => [
            ['category' => 'Meals', 'particular' => 'Snacks', 'quantity' => '100', 'unit' => 'pax', 'unit_price' => '25'],
        ],
    ])->assertRedirect(route('proposals.show', $proposal));

    $proposal->refresh();

    expect($proposal->school_year)->toBe('2027-2028')
        ->and($proposal->items)->toHaveCount(1)
        ->and($proposal->budgetTotal())->toBe(2500.0);
});

it('lists this school year by default and finds proposals by a particular', function () {
    $officer = proposalOfficer();
    proposalFor($officer, ['title' => 'Intramurals'], [
        ['category' => 'Awards', 'particular' => 'Medals', 'quantity' => 40, 'unit' => 'pcs', 'unit_price' => 200],
    ]);
    proposalFor($officer, ['title' => 'Old Fair', 'school_year' => '2025-2026', 'starts_on' => '2025-12-01']);

    $this->actingAs($officer)->get('/proposals')
        ->assertOk()->assertSee('Intramurals')->assertSee('₱8,000.00')->assertDontSee('Old Fair');

    $this->get('/proposals?year=all')->assertSee('Intramurals')->assertSee('Old Fair');

    $this->get('/proposals?year=all&q=medals')->assertSee('Intramurals')->assertDontSee('Old Fair');
});

it('sends a deleted proposal to the Trash, where any officer can restore it and only the Super Admin can delete it for good', function () {
    $maker = proposalOfficer();
    $other = proposalOfficer('Secretary');
    $proposal = proposalFor($maker);

    $this->actingAs($maker)->delete(route('proposals.destroy', $proposal))
        ->assertRedirect(route('proposals.index'));

    expect(ActivityProposal::count())->toBe(0)
        ->and(ActivityProposal::onlyTrashed()->count())->toBe(1)
        ->and(ActivityProposalItem::count())->toBe(2)
        ->and(ActivityLog::where('activity', 'Moved activity proposal "Leadership Training" to Trash')->exists())->toBeTrue();
    $this->get('/proposals')->assertDontSee(route('proposals.show', $proposal->id));
    $this->actingAs($other)->get(route('trash.index'))->assertOk()->assertSee('Leadership Training');

    // Any officer may restore it; an ordinary one may not delete it for good.
    $this->actingAs($other)->post(route('trash.restore', ['proposal', $proposal->id]))->assertSessionHas('success');
    expect(ActivityProposal::count())->toBe(1);

    $proposal->delete();
    $this->actingAs($other)->delete(route('trash.destroy', ['proposal', $proposal->id]))->assertSessionHas('error');
    $this->actingAs(proposalSuperAdmin())->delete(route('trash.destroy', ['proposal', $proposal->id]))->assertSessionHas('success');

    expect(ActivityProposal::withTrashed()->count())->toBe(0)
        ->and(ActivityProposalItem::count())->toBe(0);
});

it('purges proposals that have been in the Trash past the retention days', function () {
    $old = proposalFor(proposalOfficer(), ['title' => 'Old one']);
    $old->delete();
    $this->travel((int) config('trash.retention_days') + 1)->days();
    $recent = proposalFor(proposalOfficer('Secretary'), ['title' => 'Recent one']);
    $recent->delete();

    $this->artisan('trash:purge-expired')->assertSuccessful();

    expect(ActivityProposal::withTrashed()->pluck('title')->all())->toBe(['Recent one']);
});

it('sets the status, which only the maker or the Super Admin may change', function () {
    $maker = proposalOfficer();
    $proposal = proposalFor($maker);

    expect($proposal->fresh()->status)->toBe('draft');

    $this->actingAs(proposalOfficer('Secretary'))->post(route('proposals.status', $proposal), ['status' => 'for_signing'])->assertForbidden();

    $this->actingAs($maker)->post(route('proposals.status', $proposal), ['status' => 'for_signing'])
        ->assertRedirect(route('proposals.show', $proposal));
    expect($proposal->fresh()->status)->toBe('for_signing')
        ->and(ActivityLog::where('activity', 'Updated status of activity proposal "Leadership Training" to For signing')->exists())->toBeTrue();

    // Held only after it is approved.
    $this->post(route('proposals.status', $proposal), ['status' => 'done'])->assertSessionHasErrors('status');

    $this->get('/proposals?status=for_signing')->assertSee('Leadership Training');
    $this->get('/proposals?status=approved')->assertDontSee('Leadership Training');
});

it('locks an approved proposal until the Super Admin reopens it', function () {
    $maker = proposalOfficer();
    $superAdmin = proposalSuperAdmin();
    $proposal = proposalFor($maker);

    $this->actingAs($maker)->post(route('proposals.status', $proposal), ['status' => 'approved']);
    $proposal->refresh();
    expect($proposal->isLocked())->toBeTrue()->and($proposal->approved_at)->not->toBeNull();

    // Nobody edits or deletes it, not even the Super Admin, while it is
    // approved; the edit page sends them back to the proposal to print it.
    foreach ([$maker, $superAdmin] as $user) {
        $this->actingAs($user)->get(route('proposals.edit', $proposal))
            ->assertRedirect(route('proposals.show', $proposal))->assertSessionHas('error');
        $this->actingAs($user)->put(route('proposals.update', $proposal), ['title' => 'Changed'])->assertForbidden();
        $this->actingAs($user)->delete(route('proposals.destroy', $proposal))->assertForbidden();
    }
    expect($proposal->fresh()->title)->toBe('Leadership Training')
        ->and(ActivityProposal::count())->toBe(1);

    // It can still be held or called off, but not sent back to a draft.
    $this->actingAs($maker)->post(route('proposals.status', $proposal), ['status' => 'draft'])->assertSessionHasErrors('status');
    $this->actingAs($maker)->post(route('proposals.status', $proposal), ['status' => 'done']);
    expect($proposal->fresh()->status)->toBe('done')->and($proposal->fresh()->isLocked())->toBeTrue();

    // A copy is a new draft of the copier's own.
    $this->actingAs($maker)->post(route('proposals.copy', $proposal));
    $copy = ActivityProposal::where('copied_from_id', $proposal->id)->sole();
    expect($copy->status)->toBe('draft')->and($copy->isLocked())->toBeFalse();

    $this->actingAs($maker)->post(route('proposals.reopen', $proposal))->assertForbidden();
    $this->actingAs($superAdmin)->get(route('proposals.show', $proposal))->assertSee('Reopen for changes');
    $this->actingAs($superAdmin)->post(route('proposals.reopen', $proposal))->assertRedirect(route('proposals.show', $proposal));

    $proposal->refresh();
    expect($proposal->status)->toBe('draft')->and($proposal->isLocked())->toBeFalse();
    $this->actingAs($maker)->get(route('proposals.edit', $proposal))->assertOk();
});

it('saves the signed copy in the Activity Proposals folder and approves the proposal', function () {
    Bus::fake();
    Storage::fake('local');
    $this->mock(CloudFileUploader::class)->shouldReceive('upload')->andReturn(true);

    $maker = proposalOfficer();
    $first = proposalFor($maker);
    $second = proposalFor($maker, ['title' => 'Intramurals']);

    $this->actingAs(proposalOfficer('Secretary'))->post(route('proposals.signed', $first), [
        'files' => [UploadedFile::fake()->create('signed.pdf', 20, 'application/pdf')],
    ])->assertForbidden();

    $this->actingAs($maker)->post(route('proposals.signed', $first), [
        'files' => [UploadedFile::fake()->create('notes.docx', 20)],
    ])->assertSessionHasErrors('files.0');

    $this->actingAs($maker)->post(route('proposals.signed', $first), [
        'files' => [UploadedFile::fake()->create('Leadership-Training-signed.pdf', 20, 'application/pdf')],
    ])->assertRedirect(route('proposals.show', $first));

    $folder = Folder::where('name', 'Activity Proposals')->sole();
    $file = File::where('folder_id', $folder->id)->sole();
    $first->refresh();

    expect($file->filename)->toBe('Leadership-Training-signed.pdf')
        ->and($folder->school_year)->toBe('2026-2027')
        ->and($first->signed_file_id)->toBe($file->id)
        ->and($first->status)->toBe('approved')
        ->and($first->isLocked())->toBeTrue()
        ->and(ActivityLog::where('activity', 'like', 'Uploaded 1 file(s): Leadership-Training-signed.pdf%')->exists())->toBeTrue()
        ->and(ActivityLog::where('activity', 'Updated activity proposal "Leadership Training": signed copy "Leadership-Training-signed.pdf" attached, status Approved')->exists())->toBeTrue();
    $this->get(route('proposals.show', $first))->assertSee('Leadership-Training-signed.pdf');

    // A scan filed into the folder from the Scan Inbox is picked, not uploaded again.
    $scan = File::create(['filename' => 'Intramurals-signed.pdf', 'filepath' => 'x.pdf', 'size' => 10, 'folder_id' => $folder->id, 'storage_type' => 'local']);
    $this->get(route('proposals.show', $second))->assertSee('Intramurals-signed.pdf');
    $this->post(route('proposals.signed', $second), ['file_id' => $scan->id])->assertRedirect(route('proposals.show', $second));
    expect($second->fresh()->signed_file_id)->toBe($scan->id)
        ->and(Folder::where('name', 'Activity Proposals')->count())->toBe(1);

    // Only from that folder.
    $elsewhere = File::create(['filename' => 'other.pdf', 'filepath' => 'y.pdf', 'size' => 10, 'folder_id' => Folder::create(['name' => 'General'])->id, 'storage_type' => 'local']);
    $third = proposalFor($maker, ['title' => 'Fair']);
    $this->post(route('proposals.signed', $third), ['file_id' => $elsewhere->id])->assertSessionHas('error');
    expect($third->fresh()->signed_file_id)->toBeNull()->and($third->fresh()->status)->toBe('draft');
});

it('shows an approved proposal on the student page once switched on, with its budget per category and no names', function () {
    $maker = proposalOfficer();
    $proposal = proposalFor($maker, [
        'theme'            => 'Lead with purpose',
        'objectives_intro' => 'The training aims to:',
        'status'           => 'approved',
        'approved_at'      => now(),
        'signatories'      => ['approved' => [['name' => 'DR. EXAMPLE NAME', 'position' => 'University President']]],
        'master_of_ceremony' => 'HOST EXAMPLE NAME',
    ]);
    $page = route('student.activity', [$proposal->id, 'leadership-training']);

    // Off until an officer switches it on.
    $this->get('/stuDashboard')->assertOk()->assertDontSee('id="activities"', false);
    $this->get($page)->assertNotFound();

    $this->actingAs(proposalOfficer('Secretary'))->post(route('proposals.publish', $proposal), ['is_public' => 1])->assertForbidden();
    $this->actingAs($maker)->post(route('proposals.publish', $proposal), ['is_public' => 1])
        ->assertRedirect(route('proposals.show', $proposal))->assertSessionHas('success', 'Shown on the student page.');
    expect(ActivityLog::where('activity', 'Updated activity proposal "Leadership Training": shown on the student page')->exists())->toBeTrue();
    auth()->logout();

    $this->get('/stuDashboard')->assertOk()
        ->assertSee('id="activities"', false)
        ->assertSee('<a href="#activities" data-tab="activities">', false)   // its own tab in the installed app
        ->assertSee('Leadership Training')
        ->assertSee('Upcoming')
        ->assertSee('₱4,800.00');

    $this->get($page)->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertSee('Lead with purpose')
        ->assertSee('Train officers to plan an activity')
        ->assertSee('₱4,800.00')
        ->assertSee('Meals')->assertSee('₱3,600.00')->assertSee('75%')
        ->assertSee('Supplies')->assertSee('₱1,200.00')
        ->assertDontSee('DR. EXAMPLE NAME')
        ->assertDontSee('HOST EXAMPLE NAME')
        ->assertDontSee('SG Secretary')   // personnel in the schedule
        ->assertDontSee('Certificates');  // line items stay with the officers

    // An old or missing end of the address goes to the current one.
    $this->get(route('student.activity', [$proposal->id, 'old-title']))->assertRedirect($page)->assertStatus(301);

    // Hidden again, it is gone from the student page at once.
    $this->actingAs($maker)->post(route('proposals.publish', $proposal), ['is_public' => 0]);
    auth()->logout();
    $this->get('/stuDashboard')->assertDontSee('id="activities"', false)->assertDontSee('<a href="#activities" data-tab="activities">', false);
    $this->get($page)->assertNotFound();
});

it('keeps drafts and proposals still being signed off the student page, even when switched on', function () {
    $maker = proposalOfficer();
    $draft = proposalFor($maker, ['title' => 'Draft Fair', 'is_public' => true]);
    $signing = proposalFor($maker, ['title' => 'Signing Fair', 'is_public' => true, 'status' => 'for_signing']);
    $done = proposalFor($maker, ['title' => 'Held Fair', 'is_public' => true, 'status' => 'done', 'approved_at' => now(), 'starts_on' => '2026-09-01']);

    $this->actingAs($maker)->post(route('proposals.publish', $draft), ['is_public' => 1])
        ->assertSessionHas('success', 'It will show on the student page once it is approved.');
    auth()->logout();

    $this->get('/stuDashboard')->assertSee('Held Fair')->assertSee('Done')
        ->assertDontSee('Draft Fair')->assertDontSee('Signing Fair');
    $this->get(route('student.activity', [$draft->id, $draft->slug()]))->assertNotFound();
    $this->get(route('student.activity', [$signing->id, $signing->slug()]))->assertNotFound();
    $this->get(route('student.activity', [$done->id, $done->slug()]))->assertOk();
});

/** The tab title as the browser shows it: <title> with its whitespace collapsed. */
function proposalTabTitle(string $html): string
{
    preg_match('~<title>(.*?)</title>~s', $html, $match);

    return html_entity_decode(preg_replace('/\s+/', ' ', trim($match[1] ?? '')), ENT_QUOTES);
}

it('titles the proposal pages and fills the edit form with the saved budget', function () {
    $maker = proposalOfficer();
    $proposal = proposalFor($maker, ['title' => 'Leadership <Training>']);

    $show = $this->actingAs($maker)->get(route('proposals.show', $proposal))->assertOk()->getContent();
    expect(proposalTabTitle($show))->toBe('Leadership <Training> · QSU-FMAS')
        ->and($show)->toContain('Leadership &lt;Training&gt;');

    $edit = $this->get(route('proposals.edit', $proposal))->assertOk();
    expect(proposalTabTitle($edit->getContent()))->toBe('Edit Activity Proposal · QSU-FMAS');
    $edit->assertSee('value="Certificates"', false)
        ->assertSee('value="20"', false)
        ->assertSee('₱4,800.00');
});
