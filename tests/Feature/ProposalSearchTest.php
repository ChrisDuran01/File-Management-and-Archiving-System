<?php

use App\Http\Controllers\SearchController;
use App\Models\ActivityProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function proposalSearchUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

function searchableProposal(string $title, array $particulars = []): ActivityProposal
{
    $proposal = ActivityProposal::create(['title' => $title, 'school_year' => '2026-2027', 'starts_on' => '2026-11-03']);
    foreach ($particulars as $i => $particular) {
        $proposal->items()->create([
            'position' => $i, 'category' => 'Supplies', 'particular' => $particular, 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 100,
        ]);
    }

    return $proposal;
}

test('the search bar finds activity proposals by title and by a budget row', function () {
    searchableProposal('Sportsfest 2026', ['Medals', 'Polo shirt for officials']);
    searchableProposal('Freshmen Ball', ['Sound system']);

    $this->actingAs(proposalSearchUser())->get(route('search.live', ['query' => 'sportsfest']))
        ->assertOk()
        ->assertSee('Sportsfest 2026')
        ->assertSee('Activity proposal')
        ->assertDontSee('Freshmen Ball');

    // Found by what it budgets for, and the row says why.
    $this->get(route('search.live', ['query' => 'polo']))
        ->assertSee('Sportsfest 2026')
        ->assertSee('Budget: Polo shirt for officials');

    $this->get(route('search', ['query' => 'polo']))
        ->assertOk()
        ->assertSee('Activity Proposals')
        ->assertSee('Sportsfest 2026')
        ->assertSee('1 activity proposal');
});

test('a budget row only makes its own proposal match', function () {
    searchableProposal('Tree Planting', ['Seedlings']);
    searchableProposal('Christmas Party', ['Lechon']);

    expect(app(SearchController::class)->proposals('seedlings')->pluck('title')->all())->toBe(['Tree Planting']);
});

test('a proposal in the Trash is not found', function () {
    searchableProposal('Film Showing')->delete();

    $this->actingAs(proposalSearchUser())->get(route('search', ['query' => 'film']))
        ->assertOk()
        ->assertDontSee('Film Showing');
});
