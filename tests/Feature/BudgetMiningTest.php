<?php

use App\Models\ActivityLog;
use App\Models\ActivityProposal;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BudgetRules;
use App\Services\PriceCheck;
use App\Support\BudgetItemName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // School year 2026-2027 (it starts in August).
    $this->travelTo('2026-10-28 10:00');
});

/** No officer_terms row counts as SuperAdmin - see Folder::isSuperAdmin(). */
function miningSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

function miningOfficer(): User
{
    $user = miningSuperAdmin();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Treasurer', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

/**
 * A proposal with budget rows given as [particular, unit price] or
 * [particular, unit price, unit]; approved unless 'approved_at' => null.
 */
function minedProposal(array $rows, array $attributes = []): ActivityProposal
{
    $proposal = ActivityProposal::create($attributes + [
        'title'       => 'Past activity',
        'school_year' => '2026-2027',
        'status'      => 'approved',
        'approved_at' => now(),
    ]);
    foreach ($rows as $i => $row) {
        $proposal->items()->create([
            'particular' => $row[0],
            'quantity'   => 10,
            'unit'       => $row[2] ?? 'pax',
            'unit_price' => $row[1],
            'position'   => $i,
        ]);
    }

    return $proposal->fresh('items');
}

it('treats differently typed names of one item as the same', function () {
    expect(BudgetItemName::key('Medals'))->toBe(BudgetItemName::key('medal'))
        ->and(BudgetItemName::key("Speaker's token"))->toBe(BudgetItemName::key('Token for speaker'))
        ->and(BudgetItemName::key('Trophies'))->toBe('trophy')
        ->and(BudgetItemName::same(BudgetItemName::words('Packed lunch'), BudgetItemName::words('Lunch')))->toBeTrue()
        ->and(BudgetItemName::same(BudgetItemName::words('Lights and sound'), BudgetItemName::words('Sound system')))->toBeFalse()
        ->and(BudgetItemName::unit('Pieces'))->toBe(BudgetItemName::unit('pcs.'))
        ->and(BudgetItemName::unit('persons'))->toBe('pax');
});

it('flags a price far from what approved proposals paid, and not a usual one', function () {
    foreach ([60, 65, 65, 70] as $price) {
        minedProposal([['Lunch', $price]]);
    }
    $check = new PriceCheck(0, 3);

    $result = $check->check([
        'a' => ['particular' => 'Packed lunch', 'unit' => 'pax', 'unit_price' => 140],
        'b' => ['particular' => 'Lunch', 'unit' => 'pax', 'unit_price' => 75],
        'c' => ['particular' => 'Lunch', 'unit' => 'box', 'unit_price' => 140],
        'd' => ['particular' => 'Tarpaulin', 'unit' => 'pc', 'unit_price' => 500],
    ], 2026);

    expect($result['a']['state'])->toBe('high')
        ->and($result['a']['median'])->toEqual(65)
        ->and($result['a']['summary'])->toBe('2.2× the usual ₱65')
        ->and($result['b']['state'])->toBe('usual')
        // Another unit, or an item never bought before: nothing to compare with.
        ->and($result['c'])->toBe(['state' => 'few', 'count' => 0])
        ->and($result['d'])->toBe(['state' => 'few', 'count' => 0]);
});

it('compares only with approved proposals of recent school years, at today\'s value', function () {
    // Drafts and proposals in the Trash don't count.
    minedProposal([['Lunch', 200]], ['approved_at' => null, 'status' => 'draft']);
    minedProposal([['Lunch', 200]])->delete();
    // Too old for "this school year and the 3 before".
    minedProposal([['Lunch', 20]], ['school_year' => '2021-2022']);
    // 50 two school years ago is 50 x 1.1^2 = 60.50 today.
    foreach (['2024-2025', '2024-2025', '2024-2025'] as $sy) {
        minedProposal([['Lunch', 50]], ['school_year' => $sy]);
    }

    $result = (new PriceCheck(10, 3))->check([['particular' => 'Lunch', 'unit' => 'pax', 'unit_price' => 60]], 2026);

    expect($result[0]['state'])->toBe('usual')
        ->and($result[0]['count'])->toBe(3)
        ->and($result[0]['history'][0]['today'])->toEqual(60.5)
        ->and($result[0]['summary'])->toBe('Close to the usual ₱61');
});

it('never compares a proposal with its own prices', function () {
    foreach ([60, 65] as $price) {
        minedProposal([['Lunch', $price]]);
    }
    $own = minedProposal([['Lunch', 62]]);

    expect((new PriceCheck(0, 3))->forProposal($own)[$own->items[0]->id])->toBe(['state' => 'few', 'count' => 2]);
});

it('finds items often budgeted together with Apriori', function () {
    foreach (range(1, 4) as $i) {
        minedProposal([['Medals', 150], ['Trophies', 1200], ['Certificates', 15]]);
    }
    minedProposal([['Medals', 150]]);
    minedProposal([['Certificates', 15], ['Lunch', 65]]);
    minedProposal([['Lunch', 65], ['Snacks', 25]]);
    minedProposal([['Lunch', 65], ['Snacks', 25]]);
    minedProposal([['Lunch', 65]]);

    $rules = new BudgetRules;
    $mined = $rules->mine();
    $find = fn (array $if, string $then) => collect($mined['rules'])->first(fn ($r) => $r['if'] === $if && $r['then'] === $then);
    $medalsToTrophies = $find(['medal'], 'trophy');

    // 4 of the 9 budgets had both; 4 of the 5 with Medals had Trophies;
    // Trophies are in 4 of 9, so lift = 0.8 / (4/9).
    expect($mined['baskets'])->toBe(9)
        ->and($medalsToTrophies['together'])->toBe(4)
        ->and($medalsToTrophies['support'])->toEqual(0.444)
        ->and($medalsToTrophies['confidence'])->toEqual(0.8)
        ->and($medalsToTrophies['lift'])->toEqual(1.8)
        // Three-item sets too, kept when surer than their parts: all 4 with
        // Medals and Certificates had Trophies.
        ->and($find(['certificate', 'medal'], 'trophy')['confidence'])->toEqual(1.0)
        // ...and dropped when a shorter rule is as sure: Trophies alone
        // already always come with Certificates.
        ->and($find(['trophy'], 'certificate')['confidence'])->toEqual(1.0)
        ->and($find(['medal', 'trophy'], 'certificate'))->toBeNull();

    $suggested = $rules->suggest(['Medal']);
    expect(collect($suggested)->pluck('label')->all())->toContain('Trophies', 'Certificates')
        ->and($suggested[0]['text'])->toContain('approved proposals with Medals');

    // An item already in the budget, even under a close name, isn't
    // suggested again...
    expect(collect($rules->suggest(['Medals', 'Trophy (champion)', 'Certificate printing']))->pluck('label')->all())->toBe([])
        ->and(collect($rules->suggest(['Medals', 'Trophy']))->pluck('label')->all())->toBe(['Certificates'])
        // ...but a rule applies only to its own items: both budgets with
        // Snacks had Lunch, which says nothing about "Snacks for performers".
        ->and(collect($rules->suggest(['Snacks']))->pluck('label')->all())->toBe(['Lunch'])
        ->and(collect($rules->suggest(['Snacks for performers']))->pluck('label')->all())->toBe([]);
});

it('mines nothing before there are enough approved proposals', function () {
    foreach (range(1, BudgetRules::MIN_PROPOSALS - 1) as $i) {
        minedProposal([['Medals', 150], ['Trophies', 1200]]);
    }

    expect((new BudgetRules)->mine()['rules'])->toBe([])
        ->and((new BudgetRules)->suggest(['Medals']))->toBe([]);
});

it('answers the form with the price check and suggestions', function () {
    foreach ([60, 65, 70] as $price) {
        minedProposal([['Lunch', $price], ['Snacks', 25]]);
    }
    minedProposal([['Lunch', 65], ['Snacks', 25]]);
    minedProposal([['Lunch', 65], ['Snacks', 25]]);
    minedProposal([['Tarpaulin', 500, 'pc']]);

    $this->actingAs(miningOfficer())->postJson('/proposals/budget-check', [
        'starts_on' => '2026-11-20',
        'items'     => [
            '7' => ['particular' => 'Lunch', 'unit' => 'pax', 'unit_price' => 150],
            '8' => ['particular' => '', 'unit' => '', 'unit_price' => ''],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('prices.7.state', 'high')
        ->assertJsonPath('prices.7.count', 5)
        ->assertJsonPath('prices.8', null)
        ->assertJsonPath('suggestions.0.label', 'Snacks')
        ->assertJsonPath('approved', 6)
        ->assertJsonPath('needed', BudgetRules::MIN_PROPOSALS);
});

it('keeps the officer\'s reason only for the price it was written for', function () {
    $officer = miningOfficer();

    $this->actingAs($officer)->post('/proposals', [
        'title' => 'Sportsfest',
        'items' => [
            ['particular' => 'Lunch', 'quantity' => 50, 'unit' => 'pax', 'unit_price' => 150, 'price_note' => 'Catered, drinks included', 'price_note_for' => 150],
            ['particular' => 'Snacks', 'quantity' => 50, 'unit' => 'pax', 'unit_price' => 40, 'price_note' => 'Written for the old price', 'price_note_for' => 30],
        ],
    ])->assertRedirect();

    $items = ActivityProposal::where('title', 'Sportsfest')->first()->items;
    expect($items[0]->price_note)->toBe('Catered, drinks included')
        ->and($items[1]->price_note)->toBeNull();
});

it('marks an unusual price and its reason on the proposal page', function () {
    foreach ([60, 65, 70] as $price) {
        minedProposal([['Lunch', $price]]);
    }
    $officer = miningOfficer();
    $proposal = minedProposal([['Lunch', 150]], ['title' => 'Sportsfest', 'created_by' => $officer->id, 'approved_at' => null, 'status' => 'draft']);
    $proposal->items[0]->update(['price_note' => 'Catered, drinks included']);

    $this->actingAs($officer)->get("/proposals/{$proposal->id}")
        ->assertOk()
        ->assertSee('2.3× the usual ₱65 (3 approved proposals)')
        ->assertSeeText('Reason: Catered, drinks included');
});

it('shows the Proposals tab of Reports to any officer', function () {
    foreach ([60, 65, 70] as $price) {
        minedProposal([['Lunch', $price]], ['starts_on' => '2026-09-10']);
    }
    minedProposal([['Lunch', 150]], ['title' => 'Sportsfest', 'starts_on' => '2026-10-02', 'approved_at' => null, 'status' => 'for_signing']);

    $this->actingAs(miningOfficer())->get('/reports?tab=proposals&range=90d')
        ->assertOk()
        ->assertSee('Unusual prices')
        ->assertSee('1 price looks unusual')
        ->assertSee('Sportsfest')
        ->assertSee('Often budgeted together')
        ->assertDontSee('Change these settings');
});

it('lets only the Super Admin change the price check settings', function () {
    $this->actingAs(miningOfficer())->post('/reports/price-check-settings', ['price_inflation_rate' => 5, 'price_compare_years' => 2])
        ->assertForbidden();

    $this->actingAs(miningSuperAdmin())->post('/reports/price-check-settings', ['price_inflation_rate' => 4.5, 'price_compare_years' => 2])
        ->assertRedirect('/reports?tab=proposals');

    $settings = SiteSetting::current();
    expect($settings->price_inflation_rate)->toEqual(4.5)
        ->and($settings->price_compare_years)->toBe(2)
        ->and(PriceCheck::fromSettings()->rate())->toEqual(4.5)
        ->and(ActivityLog::where('activity', 'like', 'Updated price check settings: 4.5% inflation a year%')->exists())->toBeTrue();
});
