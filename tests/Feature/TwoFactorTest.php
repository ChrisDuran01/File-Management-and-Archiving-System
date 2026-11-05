<?php

use App\Models\ActivityLog;
use App\Models\OfficerTerm;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    config(['two_factor.required' => true]);

    // The live MySQL users table has no position column (see TermHandoverTest).
    Schema::table('users', fn (Blueprint $table) => $table->dropColumn('position'));
});

/** An account; with two-factor already set up unless $withTwoFactor is false. No officer term = SuperAdmin. */
function tfUser(bool $withTwoFactor = true): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    if ($withTwoFactor) {
        $user->forceFill(['two_factor_secret' => (new Google2FA())->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();
    }

    return $user;
}

/** An ordinary officer (Secretary, 2026-2027). */
function tfOfficer(bool $withTwoFactor = true): User
{
    $user = tfUser($withTwoFactor);
    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]),
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

function tfCode(User $user): string
{
    return (new Google2FA())->getCurrentOtp($user->fresh()->two_factor_secret);
}

test('a right password alone does not sign in: the code does, and only once', function () {
    $user = tfOfficer();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest();
    expect(ActivityLog::where('activity', 'Logged in')->count())->toBe(0);

    $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Enter your code');

    $this->post(route('two-factor.challenge.submit'), ['code' => '000000'])
        ->assertSessionHasErrors('code');
    $this->assertGuest();
    expect(ActivityLog::where('user_name', $user->name)->where('activity', 'Failed two-factor code')->count())->toBe(1);

    $code = tfCode($user);
    $this->post(route('two-factor.challenge.submit'), ['code' => $code])
        ->assertRedirect('/adminDashboard');
    $this->assertAuthenticatedAs($user);
    expect(ActivityLog::where('user_name', $user->name)->where('activity', 'Logged in')->count())->toBe(1);

    // The same code can't be used again, even by someone who saw it.
    auth()->logout();
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.challenge.submit'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('the code page is useless without a right password first, and times out', function () {
    $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    $this->post(route('two-factor.challenge.submit'), ['code' => '123456'])->assertRedirect(route('login'));

    $user = tfOfficer();
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->travel(11)->minutes();
    $this->post(route('two-factor.challenge.submit'), ['code' => tfCode($user)])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Sign in again: the code step timed out.']);
    $this->assertGuest();
});

test('five wrong codes make the account wait, even for the right one', function () {
    $user = tfOfficer();
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    foreach (range(1, 5) as $try) {
        $this->post(route('two-factor.challenge.submit'), ['code' => '000000']);
    }

    $this->post(route('two-factor.challenge.submit'), ['code' => tfCode($user)])
        ->assertSessionHasErrors('code');
    $this->assertGuest();
    expect(ActivityLog::where('activity', 'Two-factor blocked - too many wrong codes')->count())->toBe(1);
});

test('a trusted device skips the code for 30 days, until the password changes', function () {
    $user = tfOfficer();
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $response = $this->post(route('two-factor.challenge.submit'), ['code' => tfCode($user), 'trust' => '1']);
    $cookieName = TrustedDevice::cookieName($user);
    $token = $response->getCookie($cookieName, decrypt: true)?->getValue();
    expect($token)->not->toBeNull()
        ->and(TrustedDevice::where('user_id', $user->id)->value('token_hash'))->toBe(hash('sha256', $token));

    auth()->logout();
    $this->withCookie($cookieName, $token)
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/adminDashboard');
    $this->assertAuthenticatedAs($user);

    // After 30 days, or a password change, it asks again.
    $user->forgetTrustedDevices();
    auth()->logout();
    $this->withCookie($cookieName, $token)
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));
});

test('a recovery code signs in once', function () {
    $user = tfOfficer();
    $codes = app(App\Services\TwoFactor::class)->newRecoveryCodes($user);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.challenge.submit'), ['recovery_code' => strtolower($codes[0])])
        ->assertRedirect('/adminDashboard')
        ->assertSessionHas('error');
    $this->assertAuthenticatedAs($user);
    expect(count($user->fresh()->two_factor_recovery_codes))->toBe(7)
        ->and(ActivityLog::where('activity', 'Logged in (recovery code)')->count())->toBe(1);

    auth()->logout();
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.challenge.submit'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
});

test('an account without two-factor is held on the setup page until it scans and confirms', function () {
    $user = tfOfficer(withTwoFactor: false);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/adminDashboard');
    $this->get('/adminDashboard')->assertRedirect(route('two-factor.setup'));
    $this->getJson(route('notifications.index'))->assertForbidden();

    $this->get(route('two-factor.setup'))
        ->assertOk()
        ->assertSee('<svg', false)
        ->assertSee('never set this up on the SG office computer or a lab computer');

    $this->post(route('two-factor.setup.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');

    $this->post(route('two-factor.setup.confirm'), ['code' => tfCode($user)])
        ->assertRedirect(route('two-factor.recovery-codes'));
    $this->get(route('two-factor.recovery-codes'))->assertOk()->assertSee('Save your recovery codes');

    expect($user->fresh()->hasTwoFactor())->toBeTrue()
        ->and(count($user->fresh()->two_factor_recovery_codes))->toBe(8);
    $this->get('/adminDashboard')->assertOk();
});

test('a new officer chooses their own password before setting up two-factor', function () {
    $user = tfOfficer(withTwoFactor: false);
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->get('/adminDashboard')->assertRedirect(route('password.change'));
    $this->actingAs($user)->get(route('password.change'))->assertOk();

    $user->forceFill(['must_change_password' => false])->save();
    $this->actingAs($user)->get('/adminDashboard')->assertRedirect(route('two-factor.setup'));
});

test('the Adviser or President resets an officer who lost their phone', function () {
    $officer = tfOfficer();
    $termId = OfficerTerm::where('user_id', $officer->id)->value('id');
    DB::table('sessions')->insert(['id' => 'officer-phone', 'user_id' => $officer->id, 'payload' => '', 'last_activity' => time()]);
    $admin = tfUser();

    $this->actingAs($admin)->get(route('officers.edit', $termId))->assertSee('Reset two-factor');
    $this->actingAs($admin)->patch(route('officers.resetTwoFactor', $termId))
        ->assertRedirect(route('officers.edit', $termId))
        ->assertSessionHas('success');

    expect($officer->fresh()->hasTwoFactor())->toBeFalse()
        ->and($officer->fresh()->two_factor_secret)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $officer->id)->count())->toBe(0)
        ->and(ActivityLog::where('activity', 'Reset two-factor for officer: ' . $officer->name)->count())->toBe(1);

    // An ordinary officer can't, and nobody resets their own here.
    $this->actingAs(tfOfficer())->patch(route('officers.resetTwoFactor', $termId))->assertForbidden();
});

test('Profile: new recovery codes, forget devices and a new phone each need the current password', function () {
    $user = tfOfficer();
    TrustedDevice::create(['user_id' => $user->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDays(30)]);

    $this->actingAs($user)->post(route('two-factor.manage'), ['action' => 'forget-devices'])
        ->assertSessionHasErrors('current_password');
    expect(TrustedDevice::count())->toBe(1);

    $this->actingAs($user)->post(route('two-factor.manage'), ['action' => 'forget-devices', 'current_password' => 'password']);
    expect(TrustedDevice::count())->toBe(0);

    $this->actingAs($user)->post(route('two-factor.manage'), ['action' => 'new-codes', 'current_password' => 'password'])
        ->assertRedirect(route('two-factor.recovery-codes'));

    $this->actingAs($user)->post(route('two-factor.manage'), ['action' => 'new-phone', 'current_password' => 'password'])
        ->assertRedirect(route('two-factor.setup'));
    expect($user->fresh()->hasTwoFactor())->toBeFalse();
});

test('a phone signs in through the app\'s own code page', function () {
    $user = tfOfficer();

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'from_app' => 1])
        ->assertRedirect(route('app.two-factor'));
    $this->post(route('two-factor.challenge.submit'), ['code' => tfCode($user)])
        ->assertRedirect(route('app.home'));
});

test('the server command resets an account when nobody can in the app', function () {
    $user = tfUser();

    $this->artisan('two-factor:reset', ['email' => $user->email])->assertSuccessful();
    $this->artisan('two-factor:reset', ['email' => 'nobody@example.com'])->assertFailed();

    expect($user->fresh()->hasTwoFactor())->toBeFalse();
});

test('End Term waits until the next President has set up two-factor', function () {
    $president = tfUser();
    $presidentPosition = DB::table('positions')->insertGetId(['position_name' => 'President', 'is_super_admin' => true]);
    DB::table('officer_terms')->insert(['user_id' => $president->id, 'position_id' => $presidentPosition, 'school_year' => '2026-2027', 'status' => 'active']);
    $next = tfUser(withTwoFactor: false);
    DB::table('officer_terms')->insert(['user_id' => $next->id, 'position_id' => $presidentPosition, 'school_year' => '2027-2028', 'status' => 'active']);

    $this->actingAs($president)->delete(route('officers.archiveAll'), ['school_year' => '2026-2027'])
        ->assertSessionHas('error');
    expect(OfficerTerm::where('school_year', '2026-2027')->value('status'))->toBe('active');

    $next->forceFill(['two_factor_secret' => (new Google2FA())->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($president)->delete(route('officers.archiveAll'), ['school_year' => '2026-2027'])
        ->assertRedirect(route('login'));
    expect(OfficerTerm::where('school_year', '2026-2027')->value('status'))->toBe('former');
});
