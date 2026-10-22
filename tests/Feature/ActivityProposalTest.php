<?php

use App\Models\ActivityLog;
use App\Models\ActivityProposal;
use App\Models\ActivityProposalItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

it('deletes a proposal together with its budget rows', function () {
    $maker = proposalOfficer();
    $proposal = proposalFor($maker);

    $this->actingAs($maker)->delete(route('proposals.destroy', $proposal))
        ->assertRedirect(route('proposals.index'));

    expect(ActivityProposal::count())->toBe(0)
        ->and(ActivityProposalItem::count())->toBe(0)
        ->and(ActivityLog::where('activity', 'Deleted activity proposal "Leadership Training"')->exists())->toBeTrue();
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
