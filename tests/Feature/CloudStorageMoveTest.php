<?php

use App\Http\Controllers\BackupController;
use App\Models\File;
use App\Services\CloudFileUploader;
use App\Services\FileResponse;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * An S3 (Backblaze-style) files disk with made-up credentials, on a host in
 * the reserved .invalid domain - one that can never be reached - so any
 * network call would fail instead of quietly succeeding.
 */
function backblazeCloudDisk(): void
{
    config(['filesystems.disks.cloud' => [
        'driver' => 's3', 'key' => 'test-key-id', 'secret' => 'test-secret', 'region' => 'us-east-005',
        'bucket' => 'qsusg-files', 'endpoint' => 'https://s3.backblaze.invalid',
        'use_path_style_endpoint' => false, 'throw' => false,
    ]]);
    Storage::forgetDisk('cloud');
}

test('on Backblaze a preview link is signed right here, for an hour, with no call to the provider', function () {
    backblazeCloudDisk();

    FileResponse::cloudLink('warm-up.pdf'); // the first call loads the AWS library - not what is measured

    $started = microtime(true);
    $link = FileResponse::cloudLink('1790824426_general-plan.docx');

    expect($link)->toContain('backblaze.invalid')
        ->toContain('1790824426_general-plan.docx')
        ->toContain('X-Amz-Signature=')
        ->toContain('X-Amz-Expires=3600')
        ->and(microtime(true) - $started)->toBeLessThan(0.1);
});

test('a preview falls back to this server\'s own copy when there is no cloud copy yet', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/1/minutes.pdf', 'pdf bytes');

    $file = new File();
    $file->forceFill(['id' => 41, 'filename' => 'minutes.pdf', 'filepath' => 'x_minutes.pdf', 'local_path' => 'uploads/1/minutes.pdf', 'storage_type' => 'local']);

    expect(FileResponse::previewUrl($file))->toBe(route('files.raw', 41));
});

test('files go to the cloud with a type from the safe list - never as a web page', function () {
    expect(FileResponse::contentTypeFor('minutes.pdf'))->toBe('application/pdf')
        ->and(FileResponse::contentTypeFor('archives/Resolutions_2026.zip'))->toBe('application/zip')
        ->and(FileResponse::contentTypeFor('page.html'))->toBe('application/octet-stream')
        ->and(FileResponse::contentTypeFor('drawing.svg'))->toBe('application/octet-stream');
});

test('an upload the storage refuses is reported as failed, not as saved', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('writeStream')->times(3)->andReturn(false); // every retry refused
    Storage::shouldReceive('disk')->with('cloud')->andReturn($disk);

    $local = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($local, 'bytes');

    expect(app(CloudFileUploader::class)->upload('x_minutes.pdf', $local))->toBeFalse();
    @unlink($local);
});

test('an accepted upload carries the file\'s type', function () {
    Storage::fake('cloud');
    $local = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($local, 'bytes');

    expect(app(CloudFileUploader::class)->upload('x_minutes.pdf', $local))->toBeTrue();
    Storage::disk('cloud')->assertExists('x_minutes.pdf');
    @unlink($local);
});

test('a backup lists every cloud file except the old backups folder, on a disk that lists properly', function () {
    Storage::fake('cloud');
    Storage::disk('cloud')->put('1790824426_general-plan.docx', 'a');
    Storage::disk('cloud')->put('archives/Resolutions_2026.zip', 'b');
    Storage::disk('cloud')->put('backups/backup_2026_10_01.zip', 'c');

    $names = collect((new ReflectionMethod(BackupController::class, 'listCloudFiles'))->invoke(new BackupController()))
        ->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['1790824426_general-plan.docx', 'archives/Resolutions_2026.zip']);
});
