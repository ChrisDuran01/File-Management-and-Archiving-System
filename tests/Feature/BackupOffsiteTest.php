<?php

use App\Http\Controllers\BackupController;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Storage::fake('cloud');
    Storage::fake('backups');
});

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function backupSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

function offsiteBackup(string $name, ?string $disk, string $created = '2026-10-17 10:00:00'): Backup
{
    $backup = Backup::forceCreate([
        'name'       => $name,
        'file_path'  => 'backups/' . $name,
        'cloud_path' => $disk ? 'backups/' . $name : null,
        'cloud_disk' => $disk,
        'local_path' => storage_path('framework/testing/not-here/' . $name),
    ]);
    $backup->forceFill(['created_at' => $created])->save();

    return $backup;
}

test('a new backup is uploaded to the backups disk, not to Supabase', function () {
    $zip = tempnam(sys_get_temp_dir(), 'bk');
    file_put_contents($zip, str_repeat('encrypted backup bytes ', 1000));

    $upload = (new ReflectionMethod(BackupController::class, 'uploadBackupOffsite'))
        ->invoke(new BackupController(), $zip, 'backups/backup_2026_10_17_100000.zip');

    expect($upload)->toBeTrue();
    Storage::disk('backups')->assertExists('backups/backup_2026_10_17_100000.zip');
    Storage::disk('cloud')->assertMissing('backups/backup_2026_10_17_100000.zip');
    expect(Storage::disk('backups')->size('backups/backup_2026_10_17_100000.zip'))->toBe(filesize($zip));

    @unlink($zip);
});

test('a backup without its local copy downloads from wherever its off-site copy is', function () {
    $admin = backupSuperAdmin();

    $new = offsiteBackup('backup_2026_10_17_100000.zip', 'backups');
    Storage::disk('backups')->put($new->cloud_path, 'new backup');

    $old = offsiteBackup('backup_2026_10_01_111953.zip', 'cloud');
    Storage::disk('cloud')->put($old->cloud_path, 'old backup');

    $this->actingAs($admin)->get(route('backup.download', $new->id))
        ->assertOk()
        ->assertDownload('backup_2026_10_17_100000.zip');
    $this->actingAs($admin)->get(route('backup.download', $old->id))
        ->assertOk()
        ->assertDownload('backup_2026_10_01_111953.zip');

    $none = offsiteBackup('backup_2026_09_22_182721.zip', null);
    $this->actingAs($admin)->get(route('backup.download', $none->id))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('restored files go back to Supabase with their own type, not as ZIPs', function () {
    expect(BackupController::contentTypeFor('1790824426_general-plan-of-action-2627.docx'))
        ->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->and(BackupController::contentTypeFor('minutes.PDF'))->toBe('application/pdf')
        ->and(BackupController::contentTypeFor('photo.jpg'))->toBe('image/jpeg')
        ->and(BackupController::contentTypeFor('archives/Resolutions_20260716_012205.zip'))->toBe('application/zip')
        ->and(BackupController::contentTypeFor('no-extension'))->toBe('application/octet-stream');
});

test('the site files (photos, letterheads, announcements) go into the backup and come back on restore', function () {
    config(['services.backup.encryption_key' => 'test-backup-key']);
    Storage::fake('public');
    Storage::disk('public')->put('site/logo.png', 'logo bytes');
    Storage::disk('public')->put('announcements/images/poster.jpg', 'poster bytes');
    Storage::disk('public')->put('letterheads/sg.pdf', 'letterhead bytes');
    Storage::disk('public')->put('.gitignore', '*');

    $controller = new BackupController();
    $zipPath = tempnam(sys_get_temp_dir(), 'bk') . '.zip';

    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->setPassword('test-backup-key');
    $added = (new ReflectionMethod($controller, 'addPublicFilesToZip'))->invoke($controller, $zip);
    // A hostile entry must never be written outside the public disk.
    $zip->addFromString('public/../../evil.php', '<?php');
    $zip->close();

    expect($added)->toBe(3); // .gitignore is left out

    // Lose them all, then restore from the ZIP.
    Storage::fake('public');
    $zip = new ZipArchive();
    $zip->open($zipPath);
    $zip->setPassword('test-backup-key');
    $stats = (new ReflectionMethod($controller, 'restoreFilesFromZip'))->invoke($controller, $zip);
    $zip->close();
    @unlink($zipPath);

    expect($stats)->toBe(['restored' => 3, 'skipped' => 1, 'failed' => 0])
        ->and(Storage::disk('public')->get('site/logo.png'))->toBe('logo bytes')
        ->and(Storage::disk('public')->get('announcements/images/poster.jpg'))->toBe('poster bytes')
        ->and(Storage::disk('public')->get('letterheads/sg.pdf'))->toBe('letterhead bytes');
    // Checked on disk: Flysystem refuses to even look up a "../" path.
    expect(file_exists(Storage::disk('public')->path('') . '../../evil.php'))->toBeFalse();
});

test('old backups are removed from the disk each one was stored on', function () {
    // 10 kept + 2 too old: one of each kind of off-site copy.
    $oldest = offsiteBackup('backup_2026_07_15_163538.zip', 'cloud', '2026-07-15 16:35:38');
    $older  = offsiteBackup('backup_2026_07_16_100000.zip', 'backups', '2026-07-16 10:00:00');
    Storage::disk('cloud')->put($oldest->cloud_path, 'x');
    Storage::disk('backups')->put($older->cloud_path, 'x');

    foreach (range(1, 10) as $day) {
        $b = offsiteBackup(sprintf('backup_2026_10_%02d_100000.zip', $day), 'backups', sprintf('2026-10-%02d 10:00:00', $day));
        Storage::disk('backups')->put($b->cloud_path, 'x');
    }

    (new BackupController())->cleanupOldBackups();

    Storage::disk('cloud')->assertMissing($oldest->cloud_path);
    Storage::disk('backups')->assertMissing($older->cloud_path);
    Storage::disk('backups')->assertExists('backups/backup_2026_10_01_100000.zip');
    expect(Backup::count())->toBe(10);
});
