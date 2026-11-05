<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

test('one IP over the student page limit gets the 429 page until the minute is up', function () {
    config(['throttle.public_pages' => 3]);

    foreach (range(1, 3) as $visit) {
        $this->get(route('student.dashboard'))->assertOk();
    }

    $this->get(route('student.dashboard'))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertSee('<title>Too many visits | QSU Student Government</title>', false)
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('Too many visits at once');

    // Another network still gets in.
    $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
        ->get(route('student.dashboard'))
        ->assertOk();

    $this->travel(61)->seconds();
    $this->get(route('student.dashboard'))->assertOk();
});

test('pages, images and downloads count separately', function () {
    config(['throttle.public_pages' => 1, 'throttle.public_downloads' => 1]);

    $this->get(route('student.dashboard'))->assertOk();
    $this->get(route('student.dashboard'))->assertTooManyRequests();

    // Over the page limit, the sign-in page is too - but not a file preview.
    $this->get(route('login'))->assertTooManyRequests();
    $this->getJson(route('files.previewStudentDashboard', 999999))->assertNotFound();
    $this->getJson(route('files.previewStudentDashboard', 999999))
        ->assertTooManyRequests()
        ->assertJsonStructure(['message']);
});

test('signed-in officer pages have no page limit', function () {
    config(['throttle.public_pages' => 1]);

    $user = App\Models\User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    foreach (range(1, 3) as $visit) {
        $this->actingAs($user)->get(route('search', ['query' => 'minutes']))->assertOk();
    }
});
