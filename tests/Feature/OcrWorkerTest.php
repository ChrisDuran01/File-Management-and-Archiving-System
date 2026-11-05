<?php

use App\Http\Controllers\BackupController;
use App\Jobs\ExtractFileOcrText;
use App\Jobs\Middleware\HoldDuringRestore;
use App\Jobs\ProcessScanBatch;
use App\Support\OcrRestoreLock;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The lock lives in the file cache: give every test an empty one (locks
    // go to lock_path, apart from the cached values).
    $dir = storage_path('framework/testing/ocr-lock-' . uniqid());
    config(['cache.stores.file.path' => $dir, 'cache.stores.file.lock_path' => $dir]);
    Cache::forgetDriver('file');

    $this->travelTo('2026-11-03 10:00');
    // Time stands still in a test: a waiting restore would wait forever.
    Sleep::fake(syncWithCarbon: true);
    Queue::fake();
    OcrRestoreLock::$restoreWaitSeconds = 1800;
});

afterEach(function () {
    OcrRestoreLock::$restoreWaitSeconds = 1800;
    Illuminate\Support\Facades\File::deleteDirectory(config('cache.stores.file.path'));
});

/** An OCR job as the "ocr" worker would hand it to its middleware. */
function ocrJobOnWorker(): ExtractFileOcrText
{
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('getConnectionName')->andReturn('database');

    $command = new ExtractFileOcrText(42);
    $command->setJob($queueJob);

    return $command;
}

test('OCR and scan batches go on their own queue', function () {
    ExtractFileOcrText::dispatch(1);
    ProcessScanBatch::dispatch(1);

    Queue::assertPushedOn('ocr', ExtractFileOcrText::class);
    Queue::assertPushedOn('ocr', ProcessScanBatch::class);
});

test('an OCR job runs and frees the lock when no restore is going on', function () {
    $ran = false;

    (new HoldDuringRestore)->handle(ocrJobOnWorker(), function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
    $lock = OcrRestoreLock::forOcrJob();
    expect($lock->get())->toBeTrue();
    $lock->release();
    Queue::assertNothingPushed();
});

test('during a restore an OCR job waits a minute as a fresh copy', function () {
    $ran = false;

    $restored = OcrRestoreLock::whileRestoring(function () use (&$ran) {
        (new HoldDuringRestore)->handle(ocrJobOnWorker(), function () use (&$ran) {
            $ran = true;
        });
    }, fn () => null);

    expect($restored)->toBeTrue();
    expect($ran)->toBeFalse();
    expect(OcrRestoreLock::restoreWaiting())->toBeFalse();
    Queue::assertPushedOn('ocr', ExtractFileOcrText::class, fn (ExtractFileOcrText $job) => $job->delay === HoldDuringRestore::RETRY_SECONDS
        && $job->job === null);
});

test('the sync queue runs an OCR job on the spot, even during a restore', function () {
    $ran = false;

    OcrRestoreLock::whileRestoring(function () use (&$ran) {
        (new HoldDuringRestore)->handle(new ExtractFileOcrText(42), function () use (&$ran) {
            $ran = true;
        });
    }, fn () => null);

    expect($ran)->toBeTrue();
    Queue::assertNothingPushed();
});

test('a restore waits for the file being read and gives up after the wait', function () {
    OcrRestoreLock::$restoreWaitSeconds = 120;

    $reading = OcrRestoreLock::forOcrJob();
    expect($reading->get())->toBeTrue();

    $restored = false;
    $notices = 0;

    $ran = OcrRestoreLock::whileRestoring(
        function () use (&$restored) {
            $restored = true;
        },
        function () use (&$notices) {
            $notices++;
        },
    );

    expect($ran)->toBeFalse();
    expect($restored)->toBeFalse();
    expect($notices)->toBe(2); // at the start and a minute later
    expect(OcrRestoreLock::restoreWaiting())->toBeFalse();

    // Once the file is read, the restore can go.
    $reading->release();
    expect(OcrRestoreLock::whileRestoring(function () use (&$restored) {
        $restored = true;
    }, fn () => null))->toBeTrue();
    expect($restored)->toBeTrue();
});

test('a restore that could not start says why', function () {
    Notification::fake();
    OcrRestoreLock::$restoreWaitSeconds = 0;
    expect(OcrRestoreLock::forOcrJob()->get())->toBeTrue();

    (new BackupController)->performRestore(999);

    $progress = Cache::get('restore_progress');
    expect($progress['stage'])->toBe('error');
    expect($progress['message'])->toContain('still being read (OCR)')
        ->toContain('Nothing was changed');
});
