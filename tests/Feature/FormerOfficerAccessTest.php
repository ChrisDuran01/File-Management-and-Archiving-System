<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function termSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An ordinary officer with one term in the 2026-2027 school year. */
function termOfficer(string $status = 'active'): User
{
    $user = termSuperAdmin();

    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);

    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => $status,
    ]);

    return $user;
}

test('a former officer cannot sign in', function () {
    $user = termOfficer('former');

    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Account disabled.']);

    $this->assertGuest();
    expect(ActivityLog::where('activity', 'Login attempt on disabled (former officer) account')->count())->toBe(1);
});

test('an officer still signed in when End Term runs is signed out on the next click', function () {
    $officer = termOfficer();

    $this->actingAs($officer)->get(route('search', ['query' => 'minutes']))->assertOk();

    // End Term hands over to a next President, who has already signed in.
    $nextPresident = termSuperAdmin();
    DB::table('officer_terms')->insert([
        'user_id'     => $nextPresident->id,
        'position_id' => DB::table('positions')->insertGetId(['position_name' => 'President', 'is_super_admin' => true]),
        'school_year' => '2027-2028',
        'status'      => 'active',
    ]);

    $this->actingAs(termSuperAdmin())
        ->delete(route('officers.archiveAll'), ['school_year' => '2026-2027'])
        ->assertSessionHas('success');

    $this->actingAs($officer)->get(route('search', ['query' => 'minutes']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Account disabled.']);

    $this->assertGuest();
    expect(ActivityLog::where('user_name', $officer->name)->where('activity', 'Logged out (officer term ended)')->count())->toBe(1);
});

test('background calls get a 401 and the phone app goes to its own sign-in page', function () {
    $this->actingAs(termOfficer('former'))->postJson(route('session.keepAlive'))
        ->assertUnauthorized()
        ->assertJson(['message' => 'Account disabled.']);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36')
        ->actingAs(termOfficer('former'))->get(route('app.home'))
        ->assertRedirect(route('app.login'));

    $this->assertGuest();
});

test('a reactivated officer can sign in again', function () {
    $officer = termOfficer('former');
    $termId  = DB::table('officer_terms')->where('user_id', $officer->id)->value('id');

    $this->actingAs(termSuperAdmin())
        ->patch(route('officers.reactivate', $termId), [
            'school_year' => '2027-2028',
            'position_id' => DB::table('officer_terms')->where('id', $termId)->value('position_id'),
        ])
        ->assertSessionHas('success');

    auth()->logout();

    $this->post('/login', ['email' => $officer->email, 'password' => 'password'])
        ->assertRedirect('/adminDashboard');

    $this->assertAuthenticatedAs($officer);
});

test('a former officer record cannot be deleted, so it can never turn into a SuperAdmin account', function () {
    $former = termOfficer('former');
    $termId = DB::table('officer_terms')->where('user_id', $former->id)->value('id');
    $admin  = termSuperAdmin();

    $this->actingAs($admin)->get(route('officers.index'))
        ->assertOk()
        ->assertSee($former->name)
        ->assertDontSee('officers/destroy');

    $this->actingAs($admin)->delete('/officers/destroy/' . $termId)->assertNotFound();
    $this->actingAs($admin)->delete('/officers/force-delete/' . $termId)->assertNotFound();

    expect(DB::table('officer_terms')->where('id', $termId)->value('status'))->toBe('former');

    auth()->logout();

    $this->post('/login', ['email' => $former->email, 'password' => 'password']);
    $this->assertGuest();
});

test('active officers and accounts with no officer record are not affected', function () {
    $this->actingAs(termOfficer())->get(route('search', ['query' => 'minutes']))->assertOk();
    $this->actingAs(termSuperAdmin())->get(route('search', ['query' => 'minutes']))->assertOk();

    auth()->logout();
    $legacy = termSuperAdmin();

    $this->post('/login', ['email' => $legacy->email, 'password' => 'password'])
        ->assertRedirect('/superAdminDashboard');
});
