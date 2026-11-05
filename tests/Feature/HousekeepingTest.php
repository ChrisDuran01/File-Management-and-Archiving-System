<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as FileFacade;

uses(RefreshDatabase::class);

// A storage folder of its own: the command deletes for real, and the real
// storage/app/backups holds the computer's backups.
beforeEach(function () {
    $this->hkStorage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hk_' . uniqid();
    mkdir($this->hkStorage . '/app/backups', 0777, true);
    mkdir($this->hkStorage . '/app/temp', 0777, true);
    $this->app->useStoragePath($this->hkStorage);
});

afterEach(function () {
    FileFacade::deleteDirectory($this->hkStorage);
});

function hkFile(string $path, int $ageSeconds): string
{
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, 'x');
    touch($path, time() - $ageSeconds);

    return $path;
}

it('deletes expired cache rows and keeps the live ones', function () {
    config(['cache.default' => 'database']);

    DB::table('cache')->insert([
        ['key' => 'gone', 'value' => 's:1:"x";', 'expiration' => time() - 60],
        ['key' => 'live', 'value' => 's:1:"x";', 'expiration' => time() + 3600],
    ]);

    $this->artisan('housekeeping:prune')->assertSuccessful();

    expect(DB::table('cache')->pluck('key')->all())->toBe(['live']);
});

it('deletes half-written backups a day old, never whole backups', function () {
    $partialOld = hkFile(storage_path('app/backups/backup_2026_03_21_000027.zip.08322c62'), 2 * 86400);
    $partialNew = hkFile(storage_path('app/backups/backup_2026_11_04_193008.zip.0839aa01'), 60);
    $whole      = hkFile(storage_path('app/backups/backup_2026_03_21_000027.zip'), 400 * 86400);
    $snapshot   = hkFile(storage_path('app/backups/pre-restore_2026_03_21_000027.zip'), 400 * 86400);

    expect($partialOld)->toStartWith($this->hkStorage);

    $this->artisan('housekeeping:prune')->assertSuccessful();

    expect(file_exists($partialOld))->toBeFalse()
        ->and(file_exists($partialNew))->toBeTrue()   // a backup may still be writing it
        ->and(file_exists($whole))->toBeTrue()
        ->and(file_exists($snapshot))->toBeTrue();
});

it('deletes temp files a week old and the folders they leave empty', function () {
    $old    = hkFile(storage_path('app/temp/extract_1777450196/sub/old.png'), 8 * 86400);
    $recent = hkFile(storage_path('app/temp/ocr_pdf_6a61dbe2826d1.pdf'), 3600);

    $this->artisan('housekeeping:prune')->assertSuccessful();

    expect(file_exists($old))->toBeFalse()
        ->and(is_dir(storage_path('app/temp/extract_1777450196')))->toBeFalse()
        ->and(file_exists($recent))->toBeTrue();
});

it('only counts what it would delete on a dry run', function () {
    config(['cache.default' => 'database']);
    DB::table('cache')->insert(['key' => 'gone', 'value' => 's:1:"x";', 'expiration' => time() - 60]);
    $partial = hkFile(storage_path('app/backups/backup_2026_03_21_000027.zip.08322c62'), 2 * 86400);

    $this->artisan('housekeeping:prune --dry-run')
        ->expectsOutputToContain('[dry-run] Would delete 1 expired cache row(s), 1 half-written backup(s)')
        ->assertSuccessful();

    expect(DB::table('cache')->count())->toBe(1)
        ->and(file_exists($partial))->toBeTrue();
});

it('lists a folder without loading its files\' full text', function () {
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    $folder = Folder::create(['name' => 'Minutes']);
    (new File())->forceFill([
        'folder_id'  => $folder->id,
        'filename'   => 'minutes.pdf',
        'filepath'   => 'f_' . uniqid() . '.pdf',
        'size'       => 10,
        'ocr_text'   => str_repeat('word ', 2000), // 10,000 characters
        'text_terms' => ['word' => 2000],
    ])->save();

    $listed = $this->actingAs($user)->get(route('folders.show', $folder->id))->assertOk()->viewData('files')->first();

    expect($listed->getAttributes())->not->toHaveKeys(['ocr_text', 'text_terms'])
        ->and(mb_strlen($listed->ocr_head))->toBe(3000)
        ->and($listed->filename)->toBe('minutes.pdf');

    // Every other column is still there, including ones added later.
    expect(array_keys($listed->getAttributes()))->toContain('folder_hint_dismissed_at', 'duplicate_of_id', 'version_no');
});
