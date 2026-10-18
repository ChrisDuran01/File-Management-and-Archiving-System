<?php

use App\Jobs\RunBackupJob;
use App\Models\Backup;
use App\Models\SiteSetting;
use App\Services\BackupScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    SiteSetting::current()->update(['backup_enabled' => true, 'backup_frequency' => 'daily']);
});

function scheduledBackupAt(string $when): Backup
{
    $backup = Backup::forceCreate(['name' => 'backup_' . str_replace([' ', ':', '-'], '_', $when) . '.zip', 'file_path' => 'backups/x.zip']);
    $backup->forceFill(['created_at' => $when])->save();

    return $backup;
}

test('the computer switched on in the morning makes the backup the 2:00 AM run missed', function () {
    scheduledBackupAt('2026-10-16 15:00:00');

    expect(app(BackupScheduler::class)->runIfDue(Carbon::parse('2026-10-17 08:15:00')))->toBeTrue();

    Bus::assertDispatched(RunBackupJob::class);
});

test('no second backup on a day that already has one, including one made with Backup now', function () {
    scheduledBackupAt('2026-10-17 07:50:00');

    expect(app(BackupScheduler::class)->runIfDue(Carbon::parse('2026-10-17 08:15:00')))->toBeFalse();

    Bus::assertNotDispatched(RunBackupJob::class);
});

test('a backup that produced nothing is retried after 3 hours, not every 15 minutes', function () {
    $scheduler = app(BackupScheduler::class);

    expect($scheduler->runIfDue(Carbon::parse('2026-10-17 08:00:00')))->toBeTrue()
        ->and($scheduler->runIfDue(Carbon::parse('2026-10-17 08:15:00')))->toBeFalse()
        ->and($scheduler->runIfDue(Carbon::parse('2026-10-17 10:45:00')))->toBeFalse()
        ->and($scheduler->runIfDue(Carbon::parse('2026-10-17 11:00:00')))->toBeTrue();

    Bus::assertDispatchedTimes(RunBackupJob::class, 2);
});

test('weekly and monthly count from Monday and from the 1st', function () {
    scheduledBackupAt('2026-10-12 09:00:00'); // a Monday

    SiteSetting::current()->update(['backup_frequency' => 'weekly']);
    expect(app(BackupScheduler::class)->isDue(Carbon::parse('2026-10-18 20:00:00')))->toBeFalse() // Sunday, same week
        ->and(app(BackupScheduler::class)->isDue(Carbon::parse('2026-10-19 08:00:00')))->toBeTrue(); // next Monday

    SiteSetting::current()->update(['backup_frequency' => 'monthly']);
    expect(app(BackupScheduler::class)->isDue(Carbon::parse('2026-10-31 20:00:00')))->toBeFalse()
        ->and(app(BackupScheduler::class)->isDue(Carbon::parse('2026-11-01 08:00:00')))->toBeTrue();
});

test('nothing is queued while automatic backups are turned off', function () {
    SiteSetting::current()->update(['backup_enabled' => false]);

    expect(app(BackupScheduler::class)->runIfDue(Carbon::parse('2026-10-17 08:15:00')))->toBeFalse();

    Bus::assertNotDispatched(RunBackupJob::class);
});

test('the scheduler runs the check every 15 minutes', function () {
    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => $e->description === 'backup-catch-up');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});

test('a check that finds nothing due is not reported as a failed task', function () {
    scheduledBackupAt(now()->toDateTimeString()); // today already has a backup

    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => $e->description === 'backup-catch-up');

    $event->run(app());

    expect($event->exitCode)->toBe(0);
    Bus::assertNotDispatched(RunBackupJob::class);
});
