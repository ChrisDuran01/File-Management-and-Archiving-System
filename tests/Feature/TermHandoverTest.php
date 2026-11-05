<?php

use App\Models\ActivityLog;
use App\Models\Folder;
use App\Models\OfficerTerm;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();

    // The live MySQL users table has no position column (the migration still
    // makes one, NOT NULL), and the officer forms create users without it.
    Schema::table('users', fn (Blueprint $table) => $table->dropColumn('position'));

    foreach (['Adviser' => true, 'President' => true, 'Vice President' => false, 'Secretary' => false] as $name => $superAdmin) {
        DB::table('positions')->insert(['position_name' => $name, 'is_super_admin' => $superAdmin]);
    }
});

const HANDOVER_TEMP = 'Temp-Pass2026!';

function handoverUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An officer holding $position in $year; returns [user, term]. */
function handoverOfficer(string $position, string $year = '2026-2027'): array
{
    $user = handoverUser();
    $term = OfficerTerm::create([
        'user_id'     => $user->id,
        'position_id' => DB::table('positions')->where('position_name', $position)->value('id'),
        'school_year' => $year,
        'term_start'  => now(),
        'status'      => 'active',
    ]);

    return [$user, $term];
}

function newNextPresident(string $email = 'juan@example.com'): array
{
    return ['mode' => 'new', 'name' => 'Juan Cruz', 'email' => $email, 'password' => HANDOVER_TEMP];
}

test('End Term needs the next President first', function () {
    [$president] = handoverOfficer('President');
    handoverOfficer('Secretary');

    $this->actingAs($president)->get(route('officers.index'))
        ->assertOk()
        ->assertSee('Next President, SY 2027-2028')
        ->assertSee('Add the next President first.')
        ->assertSee('You will be signed out.');

    $this->actingAs($president)
        ->delete(route('officers.archiveAll'), ['school_year' => '2026-2027'])
        ->assertSessionHas('error', 'Add the next President before ending the term.');

    expect(OfficerTerm::where('status', 'active')->count())->toBe(2);
});

test('a new next President must sign in first; then the term ends and the President who ended it is signed out', function () {
    [$president] = handoverOfficer('President');
    handoverOfficer('Secretary');
    $folder = Folder::create(['name' => 'Minutes', 'created_by' => $president->id, 'school_year' => '2026-2027']);

    $this->actingAs($president)
        ->post(route('officers.nextPresident.store'), newNextPresident())
        ->assertSessionHas('openHandover');

    $juan = User::where('email', 'juan@example.com')->first();
    expect($juan->must_change_password)->toBeTrue()
        ->and($juan->officerTerms()->value('school_year'))->toBe('2027-2028');

    $this->actingAs($president)->get(route('officers.index'))
        ->assertSee('Waiting for Juan Cruz to sign in')
        ->assertSee('Next President, waiting for End Term');

    $this->actingAs($president)
        ->delete(route('officers.archiveAll'), ['school_year' => '2026-2027'])
        ->assertSessionHas('error');
    expect(OfficerTerm::where('school_year', '2026-2027')->where('status', 'active')->count())->toBe(2);

    // Juan signs in with the temporary password and chooses their own.
    auth()->logout();
    $this->post('/login', ['email' => 'juan@example.com', 'password' => HANDOVER_TEMP]);
    $this->post(route('password.change.submit'), [
        'current_password' => HANDOVER_TEMP, 'password' => 'Juan-Own2027!', 'password_confirmation' => 'Juan-Own2027!',
    ]);
    expect($juan->fresh()->must_change_password)->toBeFalse();
    auth()->logout();

    $this->actingAs($president)
        ->delete(route('officers.archiveAll'), ['school_year' => '2026-2027'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('success', 'Your term has ended. Juan Cruz is now the President.');

    $this->assertGuest();
    expect(OfficerTerm::where('school_year', '2026-2027')->where('status', 'active')->count())->toBe(0)
        ->and($juan->fresh()->isActiveOfficer())->toBeTrue()
        ->and(OfficerTerm::currentSchoolYear())->toBe('2027-2028')
        ->and((bool) $folder->fresh()->is_archived)->toBeTrue()
        ->and(ActivityLog::where('user_name', $president->name)->where('activity', 'Logged out (officer term ended)')->count())->toBe(1);
});

test('the Adviser carries over into the next school year and stays signed in', function () {
    [$adviser, $adviserTerm] = handoverOfficer('Adviser');
    $adviserTerm->update(['site_photo' => 'site/officers/adviser.jpg']);
    handoverOfficer('President');
    handoverOfficer('President', '2027-2028');

    $this->actingAs($adviser)->get(route('officers.index'))
        ->assertSee("{$adviser->name} (Adviser) stays and continues in 2027-2028.")
        ->assertDontSee('You will be signed out.');

    $this->actingAs($adviser)
        ->delete(route('officers.archiveAll'), ['school_year' => '2026-2027'])
        ->assertSessionHas('success');

    $this->assertAuthenticatedAs($adviser);
    $active = OfficerTerm::where('user_id', $adviser->id)->where('status', 'active')->get();
    expect($adviserTerm->fresh()->status)->toBe('former')
        ->and($active)->toHaveCount(1)
        ->and($active->first()->school_year)->toBe('2027-2028')
        ->and($active->first()->site_photo)->toBe('site/officers/adviser.jpg');
});

test('a re-elected President picked from the system keeps signing in', function () {
    [$president] = handoverOfficer('President');
    handoverOfficer('Vice President');

    $this->actingAs($president)->get(route('officers.index'))
        ->assertSee("{$president->name} - President 2026-2027");

    $this->actingAs($president)
        ->post(route('officers.nextPresident.store'), ['mode' => 'existing', 'user_id' => $president->id])
        ->assertSessionHas('success');

    $this->actingAs($president)
        ->delete(route('officers.archiveAll'), ['school_year' => '2026-2027'])
        ->assertSessionHas('success');

    $this->assertAuthenticatedAs($president);
    expect(OfficerTerm::where('status', 'active')->pluck('school_year', 'user_id')->all())
        ->toBe([$president->id => '2027-2028']);
});

test('removing the next President deletes an unused account, and leaves an officer their other term', function () {
    [$president] = handoverOfficer('President');
    [$vp, $vpTerm] = handoverOfficer('Vice President');

    // A mistyped email: never signed in to, so the account goes.
    $this->actingAs($president)->post(route('officers.nextPresident.store'), newNextPresident('jaun@example.com'));
    $this->actingAs($president)->delete(route('officers.nextPresident.destroy'))->assertSessionHas('success');
    expect(User::withTrashed()->where('email', 'jaun@example.com')->exists())->toBeFalse();

    // The Vice President who ran for President: only the new term goes.
    $this->actingAs($president)->post(route('officers.nextPresident.store'), ['mode' => 'existing', 'user_id' => $vp->id]);
    expect(OfficerTerm::incomingPresident()?->user_id)->toBe($vp->id);
    $this->actingAs($president)->delete(route('officers.nextPresident.destroy'));

    expect(OfficerTerm::incomingPresident())->toBeNull()
        ->and($vpTerm->fresh()->status)->toBe('active')
        ->and(ActivityLog::where('activity', 'like', 'Removed next President%')->count())->toBe(2);
});

test('a next President who already signed in is kept as a former officer when removed', function () {
    [$president] = handoverOfficer('President');

    $this->actingAs($president)->post(route('officers.nextPresident.store'), newNextPresident());
    $juan = User::where('email', 'juan@example.com')->first();
    $juan->forceFill(['must_change_password' => false])->save();

    $this->actingAs($president)->delete(route('officers.nextPresident.destroy'))->assertSessionHas('success');

    expect($juan->fresh())->not->toBeNull()
        ->and($juan->officerTerms()->value('status'))->toBe('former')
        ->and($juan->fresh()->isActiveOfficer())->toBeFalse();
});

test('new officers join the school year in office, and none are added while the next President waits', function () {
    [$president] = handoverOfficer('President');
    $secretaryId = DB::table('positions')->where('position_name', 'Secretary')->value('id');
    $officer = fn (string $email) => ['name' => 'Ana Reyes', 'email' => $email, 'password' => HANDOVER_TEMP, 'position_id' => $secretaryId, 'school_year' => '2030-2031'];

    $this->actingAs($president)->get(route('officers.create'))
        ->assertOk()
        ->assertSee('value="2026-2027"', false)
        ->assertSee('readonly', false);

    $this->actingAs($president)->post(route('officers.store'), $officer('ana@example.com'))->assertRedirect(route('officers.index'));
    expect(User::where('email', 'ana@example.com')->first()->officerTerms()->value('school_year'))->toBe('2026-2027');

    handoverOfficer('President', '2027-2028');

    $this->actingAs($president)->get(route('officers.create'))
        ->assertRedirect(route('officers.index'))
        ->assertSessionHas('error');
    $this->actingAs($president)->post(route('officers.store'), $officer('ben@example.com'))->assertSessionHas('error');

    expect(User::where('email', 'ben@example.com')->exists())->toBeFalse();
});

test('the student page shows one President while the next one waits for End Term', function () {
    [$president] = handoverOfficer('President');
    [$next] = handoverOfficer('President', '2027-2028');

    $this->get(route('student.dashboard'))->assertOk()
        ->assertSee($president->name)
        ->assertDontSee($next->name);
});
