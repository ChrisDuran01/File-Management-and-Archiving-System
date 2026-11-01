<?php

use App\Models\Announcement;
use App\Models\EngagementEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-25 12:00:00');
    // Its own storage folder, so the test never touches the list of a real run.
    $this->demoStorage = sys_get_temp_dir().'/engagement-demo-test-'.getmypid();
    @mkdir($this->demoStorage.'/app', 0777, true);
    $this->app->useStoragePath($this->demoStorage);
});

afterEach(function () {
    @unlink($this->demoStorage.'/app/engagement-demo.json');
    @rmdir($this->demoStorage.'/app');
    @rmdir($this->demoStorage);
});

test('the sample data shows three unusual days and removes cleanly, leaving real visits alone', function () {
    $real = EngagementEvent::create(['type' => 'visit', 'device' => 'mobile', 'first_today' => true, 'created_at' => '2026-10-20 09:00:00']);
    $realPost = new Announcement();
    $realPost->forceFill(['title' => 'Real post', 'body' => 'Real.'])->save();

    $this->artisan('engagement:demo-data')
        ->expectsOutputToContain('5 sample announcements')
        ->expectsOutputToContain('Thu, Sep 10')
        ->expectsOutputToContain('Tue, Sep 15')
        ->expectsOutputToContain('Wed, Oct 7')
        ->assertSuccessful();

    expect(EngagementEvent::count())->toBeGreaterThan(2000)
        ->and(Announcement::count())->toBe(6);

    $this->artisan('engagement:demo-data')->assertFailed();         // not twice

    $this->artisan('engagement:demo-data --remove')->assertSuccessful();

    expect(EngagementEvent::pluck('id')->all())->toBe([$real->id])
        ->and(Announcement::pluck('id')->all())->toBe([$realPost->id])
        ->and(is_file(storage_path('app/engagement-demo.json')))->toBeFalse();
});
