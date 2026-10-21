<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** No officer_terms row counts as SuperAdmin - see Folder::isSuperAdmin(). */
function notFoundSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

test('a visitor gets the custom page with the way back to the student page', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('<title>Page not found | QSU Student Government</title>', false)
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('Go to the student page')
        ->assertSee('href="'.route('student.dashboard').'#announcements"', false)
        ->assertSee('href="'.route('login').'"', false);
});

test('a missing announcement shows the same page', function () {
    $this->get(route('student.announcement', 999999))
        ->assertNotFound()
        ->assertSee("We couldn't find that page", false);
});

test('a signed-in officer is sent to their own dashboard, not the student page', function () {
    $this->actingAs(notFoundSuperAdmin())
        ->get(route('student.announcement', 999999))
        ->assertNotFound()
        ->assertSee('Go to your dashboard')
        ->assertSee('href="'.url('/superAdminDashboard').'"', false)
        ->assertDontSee('Or try one of these')
        ->assertDontSee('Sign in here');
});

test('inside the officer phone app the way back is the app', function () {
    $this->get('/app/no-such-page')
        ->assertNotFound()
        ->assertSee('Back to the app')
        ->assertSee('href="'.route('app.home').'"', false)
        ->assertDontSee('Or try one of these');
});

test('json requests still get a json 404', function () {
    $this->getJson('/no-such-page')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
