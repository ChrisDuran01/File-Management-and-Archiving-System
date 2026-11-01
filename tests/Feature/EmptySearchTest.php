<?php

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function emptySearchUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

test('pressing Enter on an empty search bar shows an empty page, not an error or every folder', function (string $url) {
    Folder::create(['name' => 'Meeting Minutes', 'school_year' => '2026-2027']);

    $this->actingAs(emptySearchUser())->get($url)
        ->assertOk()
        ->assertSee('Type a word in the search bar above to search.')
        ->assertDontSee('Meeting Minutes');
})->with([
    'no query'     => '/search',
    'empty query'  => '/search?query=',
    'only spaces'  => '/search?query=%20%20',
]);

test('the as-you-type dropdown stays empty for an empty box', function () {
    $this->actingAs(emptySearchUser())->get(route('search.live', ['query' => '']))
        ->assertOk()
        ->assertContent('');
});
