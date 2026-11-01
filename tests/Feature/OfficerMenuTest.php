<?php

use App\Models\User;
use App\Support\OfficerMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function menuUser(bool $superAdmin): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    // No officer term = Super Admin; an active term in an ordinary position = officer.
    if (! $superAdmin) {
        $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
        DB::table('officer_terms')->insert([
            'user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active',
        ]);
    }

    return $user;
}

/** @return array<string, string> letter => page name */
function menuKeys(User $user): array
{
    return collect(OfficerMenu::groups($user))->flatten(1)->pluck('name', 'key')->all();
}

test('every page in the menu has its own letter, and Administration is the Super Admin\'s only', function () {
    $admin = menuKeys(menuUser(true));
    $officer = menuKeys(menuUser(false));

    expect($admin)->toHaveCount(14)
        ->and(collect(OfficerMenu::groups(menuUser(true)))->flatten(1)->pluck('key')->duplicates()->all())->toBe([])
        ->and($admin)->toMatchArray(['h' => 'Dashboard', 'f' => 'Folders', 'i' => 'Scan Inbox', 'b' => 'Trash', 'l' => 'Activity Logs'])
        ->and($officer)->toHaveCount(11)
        ->and($officer)->not->toHaveKeys(['m', 'l', 's']);
});

test('the menu and the shortcut list show the same letters', function () {
    $html = $this->actingAs(menuUser(false))->get('/folders')->assertOk()->getContent();

    foreach (menuKeys(menuUser(false)) as $key => $name) {
        expect($html)->toContain('data-go-key="'.$key.'"')
            ->and($html)->toContain('<kbd class="kb-key">'.strtoupper($key).'</kbd></dt><dd>'.e($name).'</dd>');
    }
    expect($html)->toContain('title="Folders (G then F)"')
        ->and($html)->not->toContain('data-go-key="m"');
});
