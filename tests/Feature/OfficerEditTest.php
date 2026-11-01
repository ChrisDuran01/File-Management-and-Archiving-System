<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function editSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An officer with one term; returns [user, term id]. */
function editOfficer(string $status = 'active', bool $superAdmin = false): array
{
    $user = editSuperAdmin();
    $positionId = DB::table('positions')->insertGetId([
        'position_name'  => $superAdmin ? 'President' : 'Secretary',
        'is_super_admin' => $superAdmin,
    ]);
    $termId = DB::table('officer_terms')->insertGetId([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => $status,
    ]);

    return [$user, $termId];
}

const STRONG_TEMP = 'Temp-Pass2026!';

test('the edit page matches Add Officer, with the school year locked', function () {
    [$officer, $termId] = editOfficer();

    $this->actingAs(editSuperAdmin())->get(route('officers.edit', $termId))
        ->assertOk()
        ->assertSee('Officer Information')
        ->assertSee('value="' . e($officer->name) . '"', false)
        ->assertSee('value="2026-2027"', false)
        ->assertSee('readonly', false)
        ->assertSee('Save changes')
        ->assertSee('Cancel')
        ->assertSee('Reset Password')
        ->assertSee('name="password"', false);
});

test('saving goes back to the officers list; a wrong field is marked on the form', function () {
    [$officer, $termId] = editOfficer();
    [$other] = editOfficer();
    $admin = editSuperAdmin();
    $positionId = DB::table('officer_terms')->where('id', $termId)->value('position_id');

    $this->actingAs($admin)
        ->put(route('officers.update', $termId), ['name' => 'Juan Cruz', 'email' => 'juan@example.com', 'position_id' => $positionId])
        ->assertRedirect(route('officers.index'))
        ->assertSessionHas('success', 'Officer updated.');
    expect($officer->fresh()->name)->toBe('Juan Cruz');

    $this->actingAs($admin)
        ->from(route('officers.edit', $termId))
        ->put(route('officers.update', $termId), ['name' => 'Juan Cruz', 'email' => $other->email, 'position_id' => $positionId])
        ->assertRedirect(route('officers.edit', $termId))
        ->assertSessionHasErrors('email');

    $this->get(route('officers.edit', $termId))
        ->assertSee('field-input mono is-invalid', false);
});

test('a reset sets a temporary password that must be changed, and logs the officer out everywhere', function () {
    [$officer, $termId] = editOfficer();
    $admin = editSuperAdmin();
    DB::table('sessions')->insert(['id' => 'phone-session', 'user_id' => $officer->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($admin)
        ->patch(route('officers.resetPassword', $termId), ['password' => STRONG_TEMP])
        ->assertRedirect(route('officers.edit', $termId))
        ->assertSessionHas('success');

    $officer->refresh();
    expect(Hash::check(STRONG_TEMP, $officer->password))->toBeTrue()
        ->and($officer->must_change_password)->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $officer->id)->count())->toBe(0)
        ->and(ActivityLog::where('user_name', $admin->name)->where('activity', 'Reset password for officer: ' . $officer->name)->count())->toBe(1);

    // The officer signs in with it and is sent to choose their own.
    auth()->logout();
    $this->post('/login', ['email' => $officer->email, 'password' => STRONG_TEMP]);
    $this->get(route('officers.index'))->assertRedirect(route('password.change'));
});

test('a weak temporary password is refused', function () {
    [$officer, $termId] = editOfficer();
    $before = $officer->password;

    $this->actingAs(editSuperAdmin())
        ->from(route('officers.edit', $termId))
        ->patch(route('officers.resetPassword', $termId), ['password' => 'password'])
        ->assertRedirect(route('officers.edit', $termId))
        ->assertSessionHasErrors('password');

    expect($officer->fresh()->password)->toBe($before);
});

test('your own password and a former officer\'s are not reset here', function () {
    [$president, $ownTerm] = editOfficer('active', superAdmin: true);
    [$former, $formerTerm] = editOfficer('former');

    $this->actingAs($president)->get(route('officers.edit', $ownTerm))
        ->assertOk()
        ->assertSee('This is your own account')
        ->assertDontSee('name="password"', false);

    $this->actingAs($president)
        ->patch(route('officers.resetPassword', $ownTerm), ['password' => STRONG_TEMP])
        ->assertSessionHas('error', 'Change your own password from Profile.');

    $this->actingAs($president)
        ->patch(route('officers.resetPassword', $formerTerm), ['password' => STRONG_TEMP])
        ->assertSessionHas('error');

    expect(Hash::check(STRONG_TEMP, $president->fresh()->password))->toBeFalse()
        ->and(Hash::check(STRONG_TEMP, $former->fresh()->password))->toBeFalse();
});

test('an ordinary officer cannot reset anyone\'s password', function () {
    [$target, $termId] = editOfficer();
    [$secretary] = editOfficer();

    $this->actingAs($secretary)
        ->patch(route('officers.resetPassword', $termId), ['password' => STRONG_TEMP])
        ->assertForbidden();

    expect(Hash::check(STRONG_TEMP, $target->fresh()->password))->toBeFalse();
});
