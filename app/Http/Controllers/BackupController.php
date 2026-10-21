<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use App\Jobs\RunBackupJob;
use App\Jobs\RunRestoreJob;
use App\Models\ActivityLog;
use App\Models\Backup;
use App\Models\File;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\BackupFailed;
use App\Notifications\RestoreFailed;
use App\Services\CloudFileUploader;
use App\Services\FileResponse;
use App\Support\StudentPageCache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;

class BackupController extends Controller
{
    // ─── Tunable constants ────────────────────────────────────────────
    private const DOWNLOAD_TIMEOUT     = 180;   // seconds per file download
    private const DOWNLOAD_CONCURRENCY = 8;     // files downloaded in parallel while zipping
    private const MAX_RETRIES          = 3;     // retries per file download/upload
    private const RETRY_DELAY_MS       = 2000;  // ms between retries
    private const MAX_BACKUPS_KEPT     = 10;    // how many backups to retain
    private const UPLOAD_TIMEOUT       = 600;   // seconds allowed for the ZIP -> cloud upload
    private const PROGRESS_KEY         = 'backup_progress'; // cache key polled by the frontend
    private const PROGRESS_TTL_MIN     = 20;    // how long progress stays visible after finishing
    private const RESTORE_PROGRESS_KEY = 'restore_progress'; // separate key - a restore and a backup could in theory overlap
    private const SNAPSHOTS_KEPT       = 3;     // pre-restore copies of the database kept in storage/app/backups
    private const PUBLIC_ENTRY_PREFIX  = 'public/'; // ZIP folder for the `public` disk's files (site images, letterheads...)

    // Extensions that are already compressed (photos, PDFs, office docs -
    // themselves ZIP containers, video, existing archives). Re-running
    // DEFLATE against already-compressed bytes buys close to nothing in
    // size but still costs full CPU time - and that cost is paid entirely
    // single-threaded inside $zip->close(), after every file has already
    // downloaded, so it's pure added wall-clock time on every backup.
    private const ALREADY_COMPRESSED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic',
        'pdf', 'docx', 'xlsx', 'pptx',
        'zip', 'rar', '7z', 'gz',
        'mp3', 'mp4', 'mov', 'avi', 'webm',
    ];
    // ─────────────────────────────────────────────────────────────────

    // Tracks temp files streamed to disk so we can clean up in __destruct
    private array $tempFiles = [];

    /**
     * Kicks off a backup from the HTTP request (the SuperAdmin "Backup now"
     * button / scheduled console call) without doing any of the actual work
     * inline. The real work - performBackup() - can legitimately run for
     * well over an hour (large buckets, slow/retrying cloud uploads), and
     * running that inline in the request meant the SuperAdmin page's own
     * progress-polling requests had to compete with it for a PHP worker. On
     * `php artisan serve` (single-threaded) they couldn't be served AT ALL
     * until the backup finished - the progress bar sat frozen the whole
     * time, then a burst of queued-up poll requests all resolved to "done"
     * at once, each one independently reloading the page. Dispatching this
     * to the queue instead means the web server is always free to answer
     * /backup/progress immediately, on any server, threaded or not.
     *
     * Requires a queue worker (`php artisan queue:work`) actually running -
     * same operational dependency the digitize-hardcopy pipeline already
     * has. Without one, this silently sits queued and never runs.
     */
    public function createBackup()
    {
        $this->setProgress('starting', 1, 'Backup queued…');

        RunBackupJob::dispatch(Auth::user()?->name, request()->ip());

        return back()->with('success', 'Backup queued - this can take a while for a large archive.');
    }

    /**
     * Create a full backup:
     * 1. List every file on the cloud disk (paginated)
     * 2. Download files into a local ZIP concurrently (bounded pool)
     * 3. Upload ZIP back to the cloud disk
     * 4. Save record in database
     *
     * Runs on the queue via RunBackupJob, not inline in an HTTP request -
     * see createBackup() above for why. $initiatedBy/$initiatedByIp carry
     * who triggered it (for the activity log) since Auth::user() and
     * request()->ip() have nothing to report from inside a queue worker;
     * both are null for scheduled/system-triggered runs.
     */
    public function performBackup(?string $initiatedBy = null, ?string $initiatedByIp = null): void
    {
        // Extend PHP limits for this heavy operation
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->setProgress('starting', 1, 'Starting backup…');

        try {
            $backupFolder = storage_path('app/backups');
            if (!is_dir($backupFolder)) {
                mkdir($backupFolder, 0755, true);
            }

            $fileName = 'backup_' . now()->format('Y_m_d_His') . '.zip';
            $filePath = $backupFolder . DIRECTORY_SEPARATOR . $fileName;

            $zip = new ZipArchive();
            if ($zip->open($filePath, ZipArchive::CREATE) !== true) {
                $this->setProgress('error', 100, 'Could not create backup ZIP file.');
                return;
            }

            // DPA (RA 10173): the ZIP holds a full DB dump (emails, IPs,
            // password hashes) plus every file - never write it unencrypted.
            // No key configured = no backup, full stop, rather than silently
            // falling back to a plaintext one.
            $encryptionKey = config('services.backup.encryption_key');
            if (! $encryptionKey) {
                $zip->close();
                @unlink($filePath);
                throw new \RuntimeException('BACKUP_ENCRYPTION_KEY is not configured - refusing to write an unencrypted backup.');
            }
            $zip->setPassword($encryptionKey);

            // ==============================
            // STEP 1: FETCH FULL FILE LIST (PAGINATED)
            // ==============================

            $this->setProgress('listing', 2, 'Fetching file list from cloud storage…');
            $files = $this->listCloudFiles();

            if ($files === null) {
                $zip->close();
                @unlink($filePath);
                $this->setProgress('error', 100, 'Failed to fetch file list from Supabase.');
                return;
            }

            if (empty($files)) {
                $zip->close();
                @unlink($filePath);
                $this->setProgress('error', 100, 'No files found in Supabase bucket.');
                return;
            }

            Log::info("Backup started — {$fileName}, total files found: " . count($files));

            // ==============================
            // STEP 2: DOWNLOAD FILES CONCURRENTLY INTO THE ZIP
            // ==============================
            // Previously this fetched one file at a time - for a bucket with
            // hundreds of files, most of the total backup time was spent
            // waiting on network round trips rather than moving bytes.
            // downloadFilesIntoZip() runs several downloads in flight at
            // once, which collapses that dead time.

            $totalFiles = count($files);
            $this->setProgress('zipping', 5, "Adding files to backup… (0 / {$totalFiles})");

            $entryNames = $this->buildZipEntryNames($files);
            $stats = $this->downloadFilesIntoZip($zip, $files, $totalFiles, $entryNames);

            // ==============================
            // STEP 2a: THE SITE'S OWN FILES
            // ==============================
            // Announcement attachments and images, letterheads, officer
            // photos, the site logo - kept on the `public` disk on this
            // computer only, never in Supabase, so without this a lost
            // computer lost them for good.
            $this->setProgress('finalizing', 65, 'Adding site images, letterheads and announcement files…');
            $publicFiles = $this->addPublicFilesToZip($zip);

            // ==============================
            // STEP 2b: DUMP THE DATABASE INTO THE SAME ZIP
            // ==============================
            // A files-only backup can't rebuild anything - folder/file records,
            // users, activity logs, archives all live in the database. Without
            // this, "restoring from backup" was never actually possible.
            $this->setProgress('finalizing', 66, 'Dumping database…');
            $includesDatabase = $this->addDatabaseDumpToZip($zip);

            $this->setProgress('finalizing', 68, 'Finalizing backup archive…');
            $zip->close();

            // Clean up all temp files now that ZIP is finalized
            foreach ($this->tempFiles as $tmp) {
                if (file_exists($tmp)) @unlink($tmp);
            }
            $this->tempFiles = [];

            Log::info("Backup ZIP closed — added: {$stats['added']}, failed: {$stats['failed']}, skipped: {$stats['skipped']}, site files: {$publicFiles}, database: " . ($includesDatabase ? 'yes' : 'no'));

            if ($stats['added'] === 0 && ! $includesDatabase) {
                @unlink($filePath);
                $this->setProgress('error', 100, 'No files were successfully backed up.');
                return;
            }

            // ==============================
            // STEP 3: UPLOAD OFF-SITE, KEEP A LOCAL COPY TOO
            // ==============================
            // Redundancy means two independent locations: the local ZIP on
            // this computer, and an off-site copy on the 'backups' disk
            // (Backblaze B2) - a different account from the Supabase bucket
            // the live files sit in, so losing either one alone can still be
            // recovered from. Supabase was used before, but its free plan
            // refuses any file over 50 MB, and a full backup is bigger.

            $sizeInMB  = round(filesize($filePath) / 1024 / 1024, 2);
            $checksum  = hash_file('sha256', $filePath);
            $cloudPath = 'backups/' . $fileName;

            $this->setProgress('uploading', 70, "Uploading backup off-site ({$sizeInMB} MB)…");
            $uploaded = $this->uploadBackupOffsite($filePath, $cloudPath);

            if (! $uploaded) {
                Log::warning('Backup upload to off-site storage failed - keeping the local copy only.');
            }

            // ==============================
            // STEP 4: SAVE RECORD TO DATABASE
            // ==============================

            Backup::create([
                'name'              => $fileName,
                'file_path'         => $cloudPath,
                'cloud_path'        => $uploaded ? $cloudPath : null,
                'cloud_disk'        => $uploaded ? 'backups' : null,
                'local_path'        => $filePath,
                'includes_database' => $includesDatabase,
                'checksum'          => $checksum,
                'size'              => $sizeInMB . ' MB',
                'status'            => $uploaded ? 'Success' : 'Local only (off-site upload failed)',
            ]);

            $this->cleanupOldBackups();

            ActivityLog::create([
                'user_name'  => $initiatedBy ?? 'System',
                'activity'   => "Created backup: {$fileName} ({$sizeInMB} MB, {$stats['added']} files, {$publicFiles} site files" . ($includesDatabase ? ', includes database' : ', NO database dump') . ($uploaded ? ', stored locally + off-site' : ', LOCAL ONLY - off-site upload failed') . ')',
                'ip_address' => $initiatedByIp
            ]);

            $summary = "Backup created! Size: {$sizeInMB} MB | {$stats['added']} files + {$publicFiles} site files | database " . ($includesDatabase ? 'included' : 'NOT included') . ' | stored ' . ($uploaded ? 'locally and off-site' : 'LOCALLY ONLY (off-site upload failed)');

            $this->setProgress('done', 100, $summary, ['uploaded' => $uploaded]);

        } catch (\Throwable $e) {
            Log::error('Backup creation crashed: ' . $e->getMessage());
            $this->setProgress('error', 100, 'Backup failed: ' . $e->getMessage());

            // A silently failing backup is the most dangerous quiet failure
            // this system has - make sure SuperAdmins hear about it even
            // when the run was scheduled and nobody saw the error page.
            try {
                Notification::send(User::superAdmins(), new BackupFailed($e->getMessage()));
            } catch (\Throwable $notifyError) {
                Log::warning('Could not send backup-failure notification: ' . $notifyError->getMessage());
            }
        }
    }

    /**
     * Writes the current backup progress to cache so the frontend can poll
     * it (via progress()) while createBackup() is still running in this
     * same request. Any shared cache driver (file/database/redis) works -
     * just not 'array', which doesn't persist across requests.
     */
    private function setProgress(string $stage, int $percent, string $message, array $extra = []): void
    {
        Cache::put(self::PROGRESS_KEY, array_merge([
            'stage'     => $stage,
            'percent'   => $percent,
            'message'   => $message,
            'updatedAt' => now()->timestamp,
        ], $extra), now()->addMinutes(self::PROGRESS_TTL_MIN));
    }

    /**
     * Polled by the frontend while a backup is running to render a real
     * progress bar instead of a blank blocking page.
     */
    public function progress()
    {
        return response()->json(Cache::get(self::PROGRESS_KEY, [
            'stage'   => 'idle',
            'percent' => 0,
            'message' => '',
        ]));
    }

    // ═══════════════════════════════════════════════════════════════════
    //  RESTORE - rebuilds the live system (database + every file) from a
    //  backup ZIP. The mirror image of performBackup() above: restore the
    //  database first (so File/Folder ids exist again), then walk every
    //  other ZIP entry and re-upload it to the exact cloud path it was
    //  read from, using the id encoded in buildZipEntryNames() to look
    //  that path back up. Routed superadmin-only (see routes/web.php) -
    //  this overwrites live data and cannot be a click any Admin can make.
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Kicks off a restore from the HTTP request, same dispatch-to-queue
     * reasoning as createBackup(): re-importing a full database dump and
     * re-uploading every file can run just as long as building the backup
     * did, and must not block the SuperAdmin page's own progress polling.
     */
    public function restore($id)
    {
        Backup::findOrFail($id);

        $this->setRestoreProgress('starting', 1, 'Restore queued…');

        RunRestoreJob::dispatch((int) $id, Auth::user()?->name, request()->ip());

        return back()->with('success', 'Restore queued - this overwrites current data and can take a while for a large archive.');
    }

    /**
     * Polled by the frontend while a restore is running, same shape as
     * progress() above but on its own cache key so a restore and a backup
     * (however unwise to run at once) don't stomp on each other's bar.
     */
    public function restoreProgress()
    {
        return response()->json(Cache::get(self::RESTORE_PROGRESS_KEY, [
            'stage'   => 'idle',
            'percent' => 0,
            'message' => '',
        ]));
    }

    private function setRestoreProgress(string $stage, int $percent, string $message, array $extra = []): void
    {
        // The cache lives in the database being replaced - for a moment
        // mid-restore its table may not exist. Reporting progress must
        // never be what stops a restore.
        try {
            Cache::put(self::RESTORE_PROGRESS_KEY, array_merge([
                'stage'     => $stage,
                'percent'   => $percent,
                'message'   => $message,
                'updatedAt' => now()->timestamp,
            ], $extra), now()->addMinutes(self::PROGRESS_TTL_MIN));
        } catch (\Throwable $e) {
            Log::warning("Could not record restore progress ({$stage}): " . $e->getMessage());
        }
    }

    /**
     * Restore the database and every file from one backup ZIP.
     *
     * Order matters: the database is restored first so that File::find($id)
     * (used to map a ZIP entry back to its real cloud path) has rows to
     * look up again - restoring files before the DB would have nothing to
     * resolve those ids against.
     *
     * A database restore failure aborts before touching any files, rather
     * than re-uploading files against whatever (old or empty) File rows
     * happen to still exist - that would silently scatter content under
     * wrong or stale paths instead of failing loudly. The database itself
     * is then put back as it was - see replaceDatabase().
     */
    public function performRestore(int $id, ?string $initiatedBy = null, ?string $initiatedByIp = null): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->setRestoreProgress('starting', 1, 'Starting restore…');

        $backup = Backup::find($id);
        if (! $backup) {
            $this->setRestoreProgress('error', 100, 'Backup record not found.');
            return;
        }

        $encryptionKey = config('services.backup.encryption_key');
        if (! $encryptionKey) {
            $this->setRestoreProgress('error', 100, 'BACKUP_ENCRYPTION_KEY is not configured - cannot decrypt backups.');
            return;
        }

        $tempZipPath = null;

        try {
            $this->setRestoreProgress('fetching', 3, 'Locating backup archive…');
            [$zipPath, $isTempZip] = $this->resolveBackupZipPath($backup);
            if (! $zipPath) {
                $this->setRestoreProgress('error', 100, 'Could not locate the backup file (missing locally and in cloud storage).');
                return;
            }
            if ($isTempZip) {
                $tempZipPath = $zipPath;
            }

            // Same defense as the archive/trash download paths - never
            // decrypt a backup that doesn't match the checksum recorded at
            // creation time, since a corrupted/tampered dump is the worst
            // possible thing to blindly import into the live database.
            if ($backup->checksum) {
                $this->setRestoreProgress('verifying', 5, 'Verifying backup integrity…');
                if (! hash_equals($backup->checksum, hash_file('sha256', $zipPath))) {
                    $this->setRestoreProgress('error', 100, 'Backup checksum mismatch - the archive may be corrupted or tampered with. Restore aborted.');
                    return;
                }
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $this->setRestoreProgress('error', 100, 'Could not open the backup archive.');
                return;
            }
            $zip->setPassword($encryptionKey);

            $includesDatabase = $zip->locateName('database_backup.sql') !== false;
            $dbRestored = false;
            $snapshotName = null;

            if ($includesDatabase) {
                $snapshotName = $this->replaceDatabase($zip, $backup->name);

                if ($snapshotName === null) {
                    // replaceDatabase() has already reported what happened.
                    $zip->close();
                    return;
                }
                $dbRestored = true;
            } else {
                Log::warning("Restore of backup #{$backup->id} has no database dump inside it - restoring files only.");
            }

            $this->setRestoreProgress('files', 30, 'Restoring files…');
            $stats = $this->restoreFilesFromZip($zip);

            $zip->close();

            ActivityLog::create([
                'user_name'  => $initiatedBy ?? 'System',
                'activity'   => "Restored system from backup: {$backup->name} (" . ($dbRestored ? 'database restored' : 'database NOT restored') . ", {$stats['restored']} files restored, {$stats['skipped']} skipped, {$stats['failed']} failed"
                    . ($snapshotName ? "; the database from before is saved as {$snapshotName}" : '') . ')',
                'ip_address' => $initiatedByIp,
            ]);

            $summary = 'Restore complete - ' . ($dbRestored ? 'database restored' : 'database NOT restored')
                . ", {$stats['restored']} files restored"
                . ($stats['skipped'] > 0 ? ", {$stats['skipped']} skipped (no matching record)" : '')
                . ($stats['failed'] > 0 ? ", {$stats['failed']} failed" : '')
                . '.'
                . ($snapshotName ? " The database from before the restore is saved as {$snapshotName}." : '');

            $this->setRestoreProgress('done', 100, $summary);

        } catch (\Throwable $e) {
            Log::error('Restore crashed: ' . $e->getMessage());
            $this->setRestoreProgress('error', 100, 'Restore failed: ' . $e->getMessage());
            $this->notifyRestoreFailed($e->getMessage(), $backup->name ?? ('#' . $id));
        } finally {
            if ($tempZipPath && file_exists($tempZipPath)) {
                @unlink($tempZipPath);
            }
            foreach ($this->tempFiles as $tmp) {
                if (file_exists($tmp)) @unlink($tmp);
            }
            $this->tempFiles = [];

            // Finished or failed part-way, the database may no longer match
            // the student page's cached copy. rescue(): a problem here must
            // not turn a finished restore into a failed job.
            rescue(fn () => StudentPageCache::forget());
        }
    }

    /**
     * Get a local filesystem path to the backup ZIP, preferring the local
     * copy kept alongside the cloud one (same redundancy reasoning as
     * download() above) and falling back to pulling it from cloud storage
     * into a temp file when the local copy is gone.
     *
     * @return array{0: ?string, 1: bool} [path, wasDownloadedToATempFile]
     */
    private function resolveBackupZipPath(Backup $backup): array
    {
        if ($backup->local_path && file_exists($backup->local_path)) {
            return [$backup->local_path, false];
        }

        $disk = $backup->offsiteDisk();
        if (! $disk) {
            return [null, false];
        }

        // Streamed to a temp file rather than read into memory - a full
        // backup is easily bigger than PHP's memory limit.
        $tmp = tempnam(sys_get_temp_dir(), 'restore_zip_');

        try {
            $in = $disk->readStream($backup->cloud_path);
            if (! $in) {
                @unlink($tmp);
                return [null, false];
            }

            $out = fopen($tmp, 'w');
            stream_copy_to_stream($in, $out);
            fclose($out);
            fclose($in);
        } catch (\Throwable $e) {
            Log::error('Failed to download backup from off-site storage for restore: ' . $e->getMessage());
            @unlink($tmp);
            return [null, false];
        }

        return [$tmp, true];
    }

    /**
     * Swaps the live database for the one in the backup: it ends up exactly
     * as it was when the backup was made, then migrated to this version of
     * the code.
     *
     * - A copy of the current database is saved first (pre-restore_*.zip,
     *   encrypted like a backup). If any later step fails it is put back, so
     *   a failed restore leaves the system as it was.
     * - Every table is dropped before the import. The dump only replaces the
     *   tables it contains, so restoring an older backup used to leave the
     *   tables created since then in place, with today's rows, while the
     *   migrations table said they didn't exist - the app broke, and
     *   `migrate` couldn't repair it ("table already exists").
     * - Pending migrations then run, so an older backup comes up with the
     *   columns and tables this code expects.
     * - The backup list and who is signed in stay as they are now - see
     *   keepAcrossRestore().
     *
     * @return string|null The saved copy's file name, or null when the
     *                     restore failed (and has been reported).
     */
    private function replaceDatabase(ZipArchive $zip, string $backupName): ?string
    {
        $this->setRestoreProgress('database', 8, 'Saving a copy of the current database first…');

        $snapshot = $this->snapshotCurrentDatabase();
        if (! $snapshot) {
            $message = 'Could not save a copy of the current database, so nothing was changed. See logs for details.';
            $this->setRestoreProgress('error', 100, $message);
            $this->notifyRestoreFailed($message, $backupName);
            return null;
        }

        $kept = $this->rowsToKeepAcrossRestore();

        $this->setRestoreProgress('database', 10, 'Restoring database…');

        try {
            $restored = $this->restoreDatabaseFromZip($zip) && $this->migrateRestoredDatabase();
        } catch (\Throwable $e) {
            Log::error('Database restore crashed: ' . $e->getMessage());
            $restored = false;
        }

        if (! $restored) {
            $message = $this->rollbackDatabase($snapshot)
                ? 'Database restore failed, so the database was put back exactly as it was. Nothing was changed. See logs for details.'
                : 'Database restore failed, and putting the previous database back failed too. Its copy is saved as '
                    . basename($snapshot) . ' in storage/app/backups. See logs for details.';
            $this->setRestoreProgress('error', 100, $message);
            $this->notifyRestoreFailed($message, $backupName);
            return null;
        }

        try {
            $this->keepAcrossRestore($kept);
        } catch (\Throwable $e) {
            // The data is restored; only the housekeeping is incomplete.
            Log::error('Restore: could not carry the backup list / sign-ins across: ' . $e->getMessage());
        }

        return basename($snapshot);
    }

    /**
     * Saves the current database as storage/app/backups/pre-restore_*.zip,
     * encrypted with the backup key like a backup. The newest SNAPSHOTS_KEPT
     * stay after the restore too, as the way back if the wrong backup was
     * restored.
     */
    private function snapshotCurrentDatabase(): ?string
    {
        $folder = storage_path('app/backups');
        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $sqlPath = $this->tempPath('prerestore_', '.sql');
        if (! $this->dumpDatabaseTo($sqlPath)) {
            return null;
        }

        $zipPath = $folder . DIRECTORY_SEPARATOR . 'pre-restore_' . now()->format('Y_m_d_His') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            Log::error('Could not create the pre-restore copy: ' . $zipPath);
            return null;
        }
        $zip->setPassword(config('services.backup.encryption_key'));
        $zip->addFile($sqlPath, 'database_backup.sql');
        $zip->setEncryptionName('database_backup.sql', ZipArchive::EM_AES_256);

        if (! $zip->close()) {
            Log::error('Could not write the pre-restore copy: ' . $zipPath);
            @unlink($zipPath);
            return null;
        }

        $copies = glob($folder . DIRECTORY_SEPARATOR . 'pre-restore_*.zip') ?: [];
        rsort($copies); // names carry the time, so newest first
        foreach (array_slice($copies, self::SNAPSHOTS_KEPT) as $old) {
            @unlink($old);
        }

        return $zipPath;
    }

    /**
     * Extracts database_backup.sql from the (already password-set) ZIP,
     * empties the app's database and pipes the dump into `mysql` - the
     * counterpart to addDatabaseDumpToZip()'s mysqldump.
     */
    private function restoreDatabaseFromZip(ZipArchive $zip): bool
    {
        $sqlPath = $this->tempPath('restoredb_', '.sql');

        if (! $this->extractZipEntryTo($zip, 'database_backup.sql', $sqlPath)) {
            Log::error('Could not extract database_backup.sql from the backup archive (wrong password or corrupted entry).');
            return false;
        }

        if (filesize($sqlPath) === 0) {
            Log::error('Extracted database dump is empty.');
            return false;
        }

        // Checked before anything is dropped: a dump that switches database
        // would be imported somewhere other than the one just emptied.
        if ($this->dumpNamesADatabase($sqlPath)) {
            Log::error('The database dump names a database (USE / CREATE DATABASE) - refusing to import it.');
            return false;
        }

        DB::connection()->getSchemaBuilder()->dropAllTables();

        return $this->importSqlFile($sqlPath);
    }

    private function dumpNamesADatabase(string $sqlPath): bool
    {
        $handle = fopen($sqlPath, 'r');

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\s*(USE\s|CREATE\s+DATABASE)/i', $line)) {
                fclose($handle);
                return true;
            }
        }

        fclose($handle);
        return false;
    }

    /** Pipes an SQL file into `mysql`, against the app's configured database. */
    private function importSqlFile(string $sqlPath): bool
    {
        $process = new Process([
            config('services.backup.mysql_path'),
            '--host=' . config('database.connections.mysql.host'),
            '--port=' . config('database.connections.mysql.port'),
            '--user=' . config('database.connections.mysql.username'),
            config('database.connections.mysql.database'),
        ]);

        $password = config('database.connections.mysql.password');
        if ($password) {
            $process->setEnv(['MYSQL_PWD' => $password]);
        }

        $handle = fopen($sqlPath, 'r');
        $process->setInput($handle);
        $process->setTimeout(900);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::error('Database import failed to run: ' . $e->getMessage());
            return false;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $process->isSuccessful()) {
            Log::error('Database import failed: ' . $process->getErrorOutput());
            return false;
        }

        return true;
    }

    /** Brings a restored (possibly older) database up to this version of the code. */
    private function migrateRestoredDatabase(): bool
    {
        $this->setRestoreProgress('database', 20, 'Updating the restored database to this version of the system…');

        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $output   = trim(Artisan::output());

        if ($exitCode !== 0) {
            Log::error('Migrations after restore failed: ' . $output);
            return false;
        }

        Log::info('Migrations after restore: ' . $output);
        return true;
    }

    /** Puts the pre-restore copy back after a failed restore. */
    private function rollbackDatabase(string $snapshotZip): bool
    {
        $this->setRestoreProgress('database', 25, 'Something failed - putting the previous database back…');

        $zip = new ZipArchive();
        if ($zip->open($snapshotZip) !== true) {
            Log::critical('Restore rollback: cannot open ' . $snapshotZip);
            return false;
        }
        $zip->setPassword(config('services.backup.encryption_key'));

        $sqlPath   = $this->tempPath('rollback_', '.sql');
        $extracted = $this->extractZipEntryTo($zip, 'database_backup.sql', $sqlPath);
        $zip->close();

        if (! $extracted) {
            Log::critical('Restore rollback: cannot read ' . $snapshotZip);
            return false;
        }

        try {
            DB::connection()->getSchemaBuilder()->dropAllTables();
            $ok = $this->importSqlFile($sqlPath);
        } catch (\Throwable $e) {
            Log::critical('Restore rollback crashed: ' . $e->getMessage());
            $ok = false;
        }

        $ok
            ? Log::warning('Restore failed - the database was put back from ' . basename($snapshotZip) . '.')
            : Log::critical('Restore failed AND the rollback failed - the previous database is in ' . $snapshotZip . '.');

        return $ok;
    }

    /**
     * Rows that describe the system as it is now rather than its data: the
     * list of backups (restoring an older one would otherwise drop every
     * newer backup from the list, though the files still exist) and who is
     * signed in (so whoever started the restore can watch it finish).
     */
    private function rowsToKeepAcrossRestore(): array
    {
        return collect(['backups', 'sessions'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->map(fn ($row) => (array) $row)->all()])
            ->all();
    }

    private function keepAcrossRestore(array $kept): void
    {
        // Cache and queued jobs come from the moment the backup was made -
        // among them the backup's own job, which would otherwise run again.
        foreach (['cache', 'cache_locks', 'jobs', 'job_batches'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        foreach ($kept as $table => $rows) {
            DB::table($table)->delete();
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }

    private function notifyRestoreFailed(string $message, string $backupName): void
    {
        try {
            Notification::send(User::superAdmins(), new RestoreFailed($message, $backupName));
        } catch (\Throwable $notifyError) {
            Log::warning('Could not send restore-failure notification: ' . $notifyError->getMessage());
        }
    }

    /** A temp file path, removed again when the backup/restore finishes. */
    private function tempPath(string $prefix, string $suffix): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);
        $this->tempFiles[] = $base;
        $this->tempFiles[] = $base . $suffix;

        return $base . $suffix;
    }

    /**
     * Walks every ZIP entry except the database dump, maps each one back to
     * its original live storage path, and re-uploads it there.
     */
    private function restoreFilesFromZip(ZipArchive $zip): array
    {
        $stats = ['restored' => 0, 'skipped' => 0, 'failed' => 0];

        $numEntries = $zip->numFiles;
        $processed  = 0;
        $lastProgAt = 0.0;

        for ($i = 0; $i < $numEntries; $i++) {
            $entryName = $zip->getNameIndex($i);
            $processed++;

            if ($entryName === false || $entryName === 'database_backup.sql' || str_ends_with($entryName, '/')) {
                $this->reportRestoreProgress($processed, $numEntries, $lastProgAt);
                continue;
            }

            if (str_starts_with($entryName, self::PUBLIC_ENTRY_PREFIX)) {
                $stats[$this->restorePublicFile($zip, $entryName)]++;
                $this->reportRestoreProgress($processed, $numEntries, $lastProgAt);
                continue;
            }

            $cloudPath = $this->resolveOriginalCloudPath($entryName);
            if ($cloudPath === null) {
                $stats['skipped']++;
                Log::warning("Restore: could not map backup entry back to a live file, skipped: {$entryName}");
                $this->reportRestoreProgress($processed, $numEntries, $lastProgAt);
                continue;
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'restore_f_');
            $this->tempFiles[] = $tmpFile;

            if (! $this->extractZipEntryTo($zip, $entryName, $tmpFile) || filesize($tmpFile) === 0) {
                $stats['failed']++;
                Log::warning("Restore: could not extract entry from archive: {$entryName}");
                $this->reportRestoreProgress($processed, $numEntries, $lastProgAt);
                continue;
            }

            if ($this->uploadFileToCloud($tmpFile, $cloudPath)) {
                $stats['restored']++;
            } else {
                $stats['failed']++;
                Log::warning("Restore: failed to re-upload {$entryName} to {$cloudPath}");
            }

            $this->reportRestoreProgress($processed, $numEntries, $lastProgAt);
        }

        return $stats;
    }

    /**
     * Puts one site file (see addPublicFilesToZip()) back on the `public`
     * disk at the same path, overwriting what is there.
     *
     * @return string 'restored', 'skipped' or 'failed' - a restoreFilesFromZip() stats key
     */
    private function restorePublicFile(ZipArchive $zip, string $entryName): string
    {
        $relative = substr($entryName, strlen(self::PUBLIC_ENTRY_PREFIX));

        // Never write outside the public disk, whatever a ZIP says.
        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, '\\')
            || str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:/', $relative)) {
            Log::warning("Restore: unsafe site file path skipped: {$entryName}");
            return 'skipped';
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'restore_p_');
        $this->tempFiles[] = $tmpFile;

        if (! $this->extractZipEntryTo($zip, $entryName, $tmpFile)) {
            Log::warning("Restore: could not extract site file: {$entryName}");
            return 'failed';
        }

        $stream = fopen($tmpFile, 'r');

        try {
            $written = Storage::disk('public')->writeStream($relative, $stream);
        } catch (\Throwable $e) {
            Log::warning("Restore: could not write site file {$relative}: " . $e->getMessage());
            $written = false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $written ? 'restored' : 'failed';
    }

    /**
     * Reverses buildZipEntryNames(): given a ZIP entry path, resolves the
     * real cloud storage path it was originally read from.
     *
     * - "archives/…" entries were never renamed going in (FolderArchiver
     *   already organizes them meaningfully), so the entry name IS the
     *   cloud path.
     * - "folders/<year>/<folder>/<id>_<display name>" entries carry the
     *   file's database id as the prefix before the first underscore -
     *   the one part of the display name guaranteed not to collide.
     *   Looking that id up (including soft-deleted rows, same as
     *   buildZipEntryNames() when it built the name) gives back the real
     *   filepath column.
     *
     * Returns null for anything that can't be mapped (id no longer exists,
     * unrecognized layout) rather than guessing - a skipped file is
     * recoverable by hand from the ZIP; a wrongly-placed one is not.
     */
    private function resolveOriginalCloudPath(string $entryName): ?string
    {
        if (str_starts_with($entryName, 'archives/')) {
            return $entryName;
        }

        if (str_starts_with($entryName, 'folders/')) {
            $basename = basename($entryName);
            if (preg_match('/^(\d+)_/', $basename, $m)) {
                $file = File::withTrashed()->find((int) $m[1]);
                if ($file && $file->filepath) {
                    return $file->filepath;
                }
            }
        }

        return null;
    }

    /**
     * Streams one entry out of an (already password-set) ZIP into a local
     * file. Used for both the database dump and every individual file entry
     * - streaming rather than ZipArchive::extractTo() keeps this working the
     * same way regardless of how deep the entry's path is or whether that
     * path exists as a real directory anywhere.
     */
    private function extractZipEntryTo(ZipArchive $zip, string $entryName, string $destPath): bool
    {
        $stream = $zip->getStream($entryName);
        if ($stream === false) {
            return false;
        }

        $out = fopen($destPath, 'w');
        if ($out === false) {
            fclose($stream);
            return false;
        }

        while (! feof($stream)) {
            fwrite($out, fread($stream, 1024 * 1024));
        }

        fclose($out);
        fclose($stream);

        return file_exists($destPath);
    }

    /**
     * Reports file-restore progress (30%-95% of the overall bar), throttled
     * by time the same way reportZipProgress() is.
     */
    private function reportRestoreProgress(int $processed, int $totalEntries, float &$lastProgAt): void
    {
        $now = microtime(true);
        if ($now - $lastProgAt < 0.25 && $processed !== $totalEntries) {
            return;
        }
        $lastProgAt = $now;

        $percent = 30 + (int) round(($processed / max(1, $totalEntries)) * 65);
        $this->setRestoreProgress('files', min(95, $percent), "Restoring files… ({$processed} / {$totalEntries})");
    }

    /**
     * Dumps the full database via mysqldump and adds it to the ZIP as
     * database_backup.sql. Failure here doesn't abort the whole backup - a
     * files-only backup is still better than none - but is reported clearly
     * so it's never mistaken for a full one.
     */
    private function addDatabaseDumpToZip(ZipArchive $zip): bool
    {
        $sqlPath = tempnam(sys_get_temp_dir(), 'dbdump_') . '.sql';

        if (! $this->dumpDatabaseTo($sqlPath)) {
            @unlink($sqlPath);
            return false;
        }

        $zip->addFile($sqlPath, 'database_backup.sql');
        $zip->setEncryptionName('database_backup.sql', ZipArchive::EM_AES_256);
        $this->applyFastCompression($zip, 'database_backup.sql');
        $this->tempFiles[] = $sqlPath; // stays alive until $zip->close(), cleaned up after

        return true;
    }

    /**
     * Adds every file on the `public` disk (site images, letterheads,
     * officer photos, announcement files) to the ZIP under "public/",
     * encrypted like everything else. Returns how many were added.
     */
    private function addPublicFilesToZip(ZipArchive $zip): int
    {
        $disk  = Storage::disk('public');
        $added = 0;

        foreach ($disk->allFiles() as $relative) {
            // .gitignore / .htaccess belong to the code, not the data.
            if (str_starts_with(basename($relative), '.')) {
                continue;
            }

            $entryName = self::PUBLIC_ENTRY_PREFIX . $relative;

            if (! $zip->addFile($disk->path($relative), $entryName)) {
                Log::warning("Backup: could not add site file {$relative}");
                continue;
            }

            $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256);
            $this->applyFastCompression($zip, $entryName);
            $added++;
        }

        return $added;
    }

    /** mysqldump of the app's database into $sqlPath - for backups and pre-restore copies. */
    private function dumpDatabaseTo(string $sqlPath): bool
    {
        $process = new Process([
            config('services.backup.mysqldump_path'),
            '--host=' . config('database.connections.mysql.host'),
            '--port=' . config('database.connections.mysql.port'),
            '--user=' . config('database.connections.mysql.username'),
            '--single-transaction',
            '--routines',
            '--result-file=' . $sqlPath,
            config('database.connections.mysql.database'),
        ]);

        $password = config('database.connections.mysql.password');
        if ($password) {
            $process->setEnv(['MYSQL_PWD' => $password]);
        }

        $process->setTimeout(300);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::error('Database dump failed to run: ' . $e->getMessage());
            @unlink($sqlPath);
            return false;
        }

        if (! $process->isSuccessful() || ! file_exists($sqlPath) || filesize($sqlPath) === 0) {
            Log::error('Database dump failed: ' . $process->getErrorOutput());
            @unlink($sqlPath);
            return false;
        }

        return true;
    }

    /**
     * List every real file on the cloud disk, at any depth - except the old
     * backups/ folder (backing up backups would grow without bound).
     *
     * On Supabase this talks to its REST API directly: that Flysystem
     * adapter's listContents() hardcodes a 100-item page with no way to page
     * past it, which would silently truncate backups of any bucket with more
     * than 100 objects. Backblaze (S3) lists properly through the disk.
     */
    private function listCloudFiles(): ?array
    {
        if (FileResponse::cloudIsSupabase()) {
            return $this->fetchFilesAtPrefix('');
        }

        try {
            return collect(Storage::disk('cloud')->allFiles())
                ->reject(fn (string $path) => str_starts_with($path, 'backups/'))
                ->map(fn (string $path) => ['name' => $path])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('Failed to list files for backup: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Recursively lists every real file under $prefix.
     *
     * Supabase Storage's list endpoint is NOT recursive - like S3 with a
     * delimiter, it returns one directory level at a time, and a subfolder
     * comes back as a bare placeholder entry (no id, no metadata) rather
     * than expanding into what's inside it. Treating that one flat call as
     * "every file" silently missed everything under archives/ (and any
     * future subfolder) - it was never even listed, so it was never backed
     * up either, despite shouldSkipFile() never being written to exclude
     * it. Recursing into every placeholder (other than the ones we already
     * mean to skip, like backups/) is what actually reaches those files.
     */
    private function fetchFilesAtPrefix(string $prefix): ?array
    {
        $all    = [];
        $limit  = 1000;
        $offset = 0;

        $disk    = config('filesystems.disks.cloud');
        $listUrl = rtrim((string) $disk['endpoint'], '/') . '/storage/v1/object/list/' . $disk['bucket'];

        do {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $disk['key'],
                'apikey'        => $disk['key'],
            ])->post($listUrl, [
                'limit'  => $limit,
                'offset' => $offset,
                'prefix' => $prefix,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to list files for backup', [
                    'prefix' => $prefix,
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $page = $response->json();

            foreach ($page as $item) {
                $name = $item['name'] ?? null;
                // '.' is Supabase's placeholder for "this folder itself" -
                // not a real object, nothing to recurse into or download.
                if (!$name || $name === '.') {
                    continue;
                }

                $fullName = $prefix . $name;

                // A real object always carries an id + metadata; a
                // subfolder placeholder carries neither - that's the only
                // signal the API gives to tell the two apart.
                $isFolder = ($item['id'] ?? null) === null;

                if ($isFolder) {
                    // Never descend into the backups folder itself - that's
                    // this process's own output, and backing up backups of
                    // backups would grow without bound. (Not shouldSkipFile()
                    // here - its trailing-slash rule matches every folder
                    // name, which would skip recursing into all of them,
                    // archives/ included.)
                    if ($fullName === 'backups' || str_starts_with($fullName, 'backups/')) {
                        continue;
                    }
                    $nested = $this->fetchFilesAtPrefix($fullName . '/');
                    if ($nested === null) {
                        return null;
                    }
                    $all = array_merge($all, $nested);
                    continue;
                }

                $all[] = ['name' => $fullName] + $item;
            }

            $offset += $limit;

            // The list endpoint returns fewer than $limit once we've hit the end
        } while (count($page) === $limit);

        return $all;
    }

    /**
     * Maps each file's flat, auto-generated storage path (e.g.
     * "1788866522_2_0_6a9fefda40488.png" - meaningless to a human) to a
     * ZIP entry path that actually says something: which school year and
     * folder it was filed under, and its real display name - e.g.
     * "folders/2025-2026/Meeting Minutes/238_Resolution.pdf". Without this,
     * opening the backup manually meant a flat pile of files with no
     * indication of where anything belonged short of cross-referencing the
     * SQL dump by hand.
     *
     * Archive entries (already organized by FolderArchiver under
     * archives/) are left untouched. Anything not found in the files table
     * (shouldn't normally happen) falls back to its raw storage path rather
     * than being dropped.
     *
     * The numeric id prefix is what actually guarantees uniqueness inside
     * the ZIP - two files can share a display name in the same folder
     * (re-uploads, duplicates), but never share an id.
     */
    private function buildZipEntryNames(array $files): array
    {
        $fileMeta = File::withTrashed()
            ->with(['folder' => fn ($q) => $q->withTrashed()])
            ->get()
            ->keyBy('filepath');

        $entryNames = [];

        foreach ($files as $file) {
            $name = $file['name'] ?? null;
            if (!$name || str_starts_with($name, 'archives/')) {
                continue;
            }

            $record = $fileMeta->get($name);
            if (!$record) {
                // No metadata to rename with - fulfilled() falls back to
                // $name, except where that would be mistaken for one of the
                // site's own files on restore (see addPublicFilesToZip()).
                if (str_starts_with($name, self::PUBLIC_ENTRY_PREFIX)) {
                    $entryNames[$name] = 'cloud-' . $name;
                }
                continue;
            }

            $year       = $record->school_year ?: ($record->folder->school_year ?? null) ?: 'unknown-year';
            $folderName = $record->folder->name ?? 'root';
            $display    = $record->filename ?: basename($name);

            $entryNames[$name] = sprintf(
                'folders/%s/%s/%d_%s',
                $this->sanitizeForZipPath((string) $year),
                $this->sanitizeForZipPath($folderName),
                $record->id,
                $this->sanitizeForZipPath($display)
            );
        }

        return $entryNames;
    }

    /**
     * Strips characters that are unsafe or ambiguous inside a ZIP entry
     * path - path separators would silently create extra nesting instead
     * of appearing in the name, and the rest aren't valid across common
     * filesystems if someone extracts the backup and copies files around.
     */
    private function sanitizeForZipPath(string $name): string
    {
        $clean = preg_replace('/[\\\\\/:*?"<>|]/', '_', $name);
        $clean = trim($clean);

        return $clean !== '' ? $clean : 'unnamed';
    }

    /**
     * Downloads every eligible file into the ZIP concurrently instead of
     * one at a time. The old approach fetched files sequentially, so total
     * time was roughly (per-file round trip) x (file count) - for a bucket
     * with hundreds of files, most of that was time spent waiting on
     * network latency, not transferring bytes. Running a bounded pool of
     * downloads in flight at once (DOWNLOAD_CONCURRENCY, not unlimited -
     * stays polite to Supabase and to this server's own memory/file
     * descriptors) collapses that dead time. Each download still streams
     * straight to its own temp file on disk via Guzzle's 'sink' option, so
     * memory usage per file is unchanged from before - only the waiting
     * happens in parallel now, not the buffering.
     *
     * Guzzle's Pool needs request-level control the Storage facade doesn't
     * expose, so each file is fetched by URL: on Supabase its REST endpoint
     * (with the service key), on Backblaze a link signed right here (and no
     * Authorization header - B2 refuses a signed link that also carries one).
     */
    private function downloadFilesIntoZip(ZipArchive $zip, array $files, int $totalFiles, array $entryNames = []): array
    {
        $stats = ['added' => 0, 'skipped' => 0, 'failed' => 0];

        $toFetch = [];
        foreach ($files as $file) {
            $name = $file['name'] ?? null;
            if (!$name || $this->shouldSkipFile($name)) {
                $stats['skipped']++;
                continue;
            }
            $toFetch[] = $name;
        }

        $processed  = $stats['skipped'];
        $lastProgAt = 0.0;
        $this->reportZipProgress($processed, $totalFiles, $lastProgAt);

        if (empty($toFetch)) {
            return $stats;
        }

        $disk       = config('filesystems.disks.cloud');
        $onSupabase = FileResponse::cloudIsSupabase();

        // Per-request retries for transient errors (timeouts, 5xx) - a 404
        // means the file is genuinely gone, so it isn't worth retrying.
        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(
            function ($retries, $request, $response = null, $exception = null) {
                if ($retries >= self::MAX_RETRIES - 1) {
                    return false;
                }
                if ($exception !== null) {
                    return true;
                }
                return $response !== null && $response->getStatusCode() >= 500;
            },
            fn ($retries) => self::RETRY_DELAY_MS
        ));

        $client = new GuzzleClient($onSupabase ? [
            'base_uri' => rtrim((string) $disk['endpoint'], '/') . '/storage/v1/',
            'handler'  => $stack,
            'timeout'  => self::DOWNLOAD_TIMEOUT,
            'headers'  => [
                'Authorization' => 'Bearer ' . $disk['key'],
                'apikey'        => $disk['key'],
            ],
        ] : [
            'handler'  => $stack,
            'timeout'  => self::DOWNLOAD_TIMEOUT,
        ]);

        // Two hours: long enough for a slow backup to reach the last file.
        $urlFor = $onSupabase
            ? fn (string $name) => 'object/' . $disk['bucket'] . '/' . $name
            : fn (string $name) => FileResponse::cloudLink($name, 120);

        $tempPaths = [];
        foreach ($toFetch as $name) {
            $tempPaths[$name] = tempnam(sys_get_temp_dir(), 'bkp_');
        }

        $requests = function () use ($client, $urlFor, $toFetch, $tempPaths) {
            foreach ($toFetch as $name) {
                $tmpPath = $tempPaths[$name];
                yield $name => function (array $opts) use ($client, $urlFor, $name, $tmpPath) {
                    return $client->getAsync($urlFor($name), array_merge($opts, [
                        'sink' => $tmpPath,
                    ]));
                };
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => self::DOWNLOAD_CONCURRENCY,
            'fulfilled'   => function ($response, $name) use ($zip, &$stats, $tempPaths, &$processed, $totalFiles, &$lastProgAt, $entryNames) {
                $tmpPath = $tempPaths[$name];

                if ($response->getStatusCode() === 200 && file_exists($tmpPath) && filesize($tmpPath) > 0) {
                    // addFile() is lazy — temp file must stay alive until zip->close()
                    // buildZipEntryNames() maps the flat, auto-generated storage
                    // path to something human-readable (folders/<year>/<folder
                    // name>/<id>_<display name>) - the raw $name is only ever
                    // used as a fallback (untracked file, or lookup miss).
                    $entryName = $entryNames[$name] ?? $name;
                    $zip->addFile($tmpPath, $entryName);
                    $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256);
                    $this->applyFastCompression($zip, $entryName);
                    $this->tempFiles[] = $tmpPath;
                    $stats['added']++;
                } else {
                    @unlink($tmpPath);
                    $stats['failed']++;
                    Log::warning("Backup download failed for: {$name} (HTTP {$response->getStatusCode()})");
                }

                $processed++;
                $this->reportZipProgress($processed, $totalFiles, $lastProgAt);
            },
            'rejected' => function ($reason, $name) use (&$stats, $tempPaths, &$processed, $totalFiles, &$lastProgAt) {
                if (file_exists($tempPaths[$name])) {
                    @unlink($tempPaths[$name]);
                }
                $stats['failed']++;
                $processed++;

                $message = $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason;
                Log::warning("Backup download errored for: {$name} — {$message}");

                $this->reportZipProgress($processed, $totalFiles, $lastProgAt);
            },
        ]);

        $pool->promise()->wait();

        return $stats;
    }

    /**
     * Reports zipping progress (5%-65% of the overall bar). Throttled by
     * time so it stays cheap even with thousands of files settling in
     * quick succession from the concurrent pool.
     */
    private function reportZipProgress(int $processed, int $totalFiles, float &$lastProgAt): void
    {
        $now = microtime(true);
        if ($now - $lastProgAt < 0.25 && $processed !== $totalFiles) {
            return;
        }
        $lastProgAt = $now;

        $percent = 5 + (int) round(($processed / max(1, $totalFiles)) * 60);
        $this->setProgress('zipping', min(65, $percent), "Adding files to backup… ({$processed} / {$totalFiles})");
    }

    /**
     * Upload the final ZIP to the cloud disk.
     *
     * This talks to the Supabase Storage REST API directly instead of going
     * through Storage::disk('cloud') - the installed Flysystem adapter's
     * writeStream() calls stream_get_contents() first, buffering the whole
     * ZIP into a PHP string before it even starts sending. For a backup
     * that can run into the hundreds of MB, that risks blowing past the
     * memory limit or the client's default request timeout, which is
     * exactly the kind of failure that previously left a backup local-only.
     * Streaming the file handle straight into the request body avoids the
     * buffering, and a generous timeout + retries (like the file-download
     * side of this class already does) make transient failures recoverable.
     * The 'progress' option reports real uploaded/total bytes so the
     * frontend can show an actual percentage instead of a blank wait.
     */
    /**
     * Streams the finished backup ZIP to the 'backups' disk (Backblaze B2),
     * retrying like the other transfers here, and only reports success once
     * the stored copy is the same size as the local one.
     */
    private function uploadBackupOffsite(string $localPath, string $cloudPath): bool
    {
        $disk = Storage::disk('backups');
        $size = filesize($localPath);

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $handle = fopen($localPath, 'r');

            if ($handle === false) {
                Log::error('Could not open backup ZIP for upload: ' . $localPath);
                return false;
            }

            try {
                $disk->writeStream($cloudPath, $handle);

                if ($disk->size($cloudPath) === $size) {
                    return true;
                }

                Log::warning("Backup off-site upload attempt {$attempt}: stored size does not match the local ZIP.");
            } catch (\Throwable $e) {
                Log::warning("Backup off-site upload attempt {$attempt} failed: " . $e->getMessage());
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }

            if ($attempt < self::MAX_RETRIES) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }
        }

        Log::error('All backup off-site upload attempts failed.');
        return false;
    }

    /**
     * Puts one restored file back in cloud storage at its original path,
     * overwriting whatever is there, with the file's own type (PDF, Word,
     * image...) - the storage serves it back with that type, and previews
     * and signed links depend on it.
     *
     * Backblaze overwrites on a normal upload (CloudFileUploader); Supabase
     * needs its REST API's x-upsert, which its Flysystem adapter doesn't send.
     */
    private function uploadFileToCloud(string $localPath, string $cloudPath): bool
    {
        if (! FileResponse::cloudIsSupabase()) {
            return app(CloudFileUploader::class)->upload($cloudPath, $localPath);
        }

        $disk   = config('filesystems.disks.cloud');
        $url    = rtrim((string) $disk['endpoint'], '/') . '/storage/v1/object/' . $disk['bucket'] . '/' . ltrim($cloudPath, '/');
        $type   = self::contentTypeFor($cloudPath);

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $handle = fopen($localPath, 'r');

            if ($handle === false) {
                Log::error('Could not open restored file for upload: ' . $localPath);
                return false;
            }

            try {
                $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $disk['key'],
                        'apikey'        => $disk['key'],
                        'x-upsert'      => 'true',
                    ])
                    ->withBody($handle, $type)
                    ->timeout(self::UPLOAD_TIMEOUT)
                    ->post($url);

                if ($response->successful() && $response->json('Id') !== null) {
                    return true;
                }

                Log::warning("Restore upload attempt {$attempt} for {$cloudPath} failed: HTTP {$response->status()} — " . $response->body());

            } catch (\Throwable $e) {
                Log::warning("Restore upload attempt {$attempt} for {$cloudPath} threw: " . $e->getMessage());
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }

            if ($attempt < self::MAX_RETRIES) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }
        }

        Log::error("All restore upload attempts for {$cloudPath} failed.");
        return false;
    }

    /** The type a file should be served with - the same short list FileResponse serves. */
    public static function contentTypeFor(string $path): string
    {
        return FileResponse::contentTypeFor($path);
    }

    /**
     * Picks the fastest sensible compression for one ZIP entry: skip
     * compression entirely (CM_STORE) for content that's already compressed
     * - it won't shrink further, so DEFLATE there is wasted CPU - otherwise
     * DEFLATE at the fastest level (1 instead of the default 6). Still
     * shrinks genuinely compressible content (the SQL dump, plain text)
     * well; just spends far less CPU getting there. AES encryption itself
     * isn't touched - it's not the bottleneck (hardware-accelerated on
     * virtually every modern CPU) and dropping to AES-128 would trade
     * security for a speedup this doesn't need.
     */
    private function applyFastCompression(ZipArchive $zip, string $entryName): void
    {
        $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

        if (in_array($ext, self::ALREADY_COMPRESSED_EXTENSIONS, true)) {
            $zip->setCompressionName($entryName, ZipArchive::CM_STORE);
            return;
        }

        $zip->setCompressionName($entryName, ZipArchive::CM_DEFLATE, 1);
    }

    // ─── Decide if a file should be excluded from backup ─────────────
    private function shouldSkipFile(string $name): bool
    {
        // Skip backup ZIPs themselves
        if (str_starts_with($name, 'backup_') && str_ends_with($name, '.zip')) {
            return true;
        }

        // Skip anything already in the backups folder
        if (str_starts_with($name, 'backups/')) {
            return true;
        }

        // Skip Supabase placeholder/folder entries
        if (str_ends_with($name, '/') || $name === '') {
            return true;
        }

        return false;
    }

    /**
     * Download a backup file from the cloud disk
     */
    public function download($id)
    {
        $backup = Backup::findOrFail($id);

        try {
            // Prefer the local copy - faster, and still works if the
            // off-site account is unreachable, which is the whole point of
            // keeping two independent copies. Both are streamed, never read
            // into memory whole.
            if ($backup->local_path && file_exists($backup->local_path)) {
                $response = response()->download($backup->local_path, $backup->name, ['Content-Type' => 'application/zip']);
            } elseif ($disk = $backup->offsiteDisk()) {
                $response = $disk->download($backup->cloud_path, $backup->name, ['Content-Type' => 'application/zip']);
            } else {
                return back()->with('error', 'This backup has no copy left to download.');
            }

            ActivityLog::create([
                'user_name'  => Auth::user()?->name ?? 'System',
                'activity'   => 'Downloaded backup: ' . $backup->name,
                'ip_address' => request()->ip()
            ]);

            return $response;

        } catch (\Throwable) {
            return back()->with('error', 'Download failed.');
        }
    }

    /**
     * Show backup list UI
     */
    public function index()
    {
        $backups = Backup::latest()->get();
        $settings = SiteSetting::current();

        return view('SuperAdmin.backup', [
            'backups'       => $backups,
            'backupEnabled' => $settings->backup_enabled,
            'frequency'     => $settings->backup_frequency,
            // The whole page is SuperAdmin-only now (routes/web.php), so
            // this is always true here; the view still checks it before
            // showing Restore.
            'isSuperAdmin'  => Auth::user() && \App\Models\Folder::isSuperAdmin(Auth::user()),
        ]);
    }

    /**
     * Enable/disable scheduled backups. Persisted on the site_settings row,
     * not the session - the scheduler (routes/console.php, run by
     * `php artisan schedule:run` with no browser session at all) needs to
     * be able to read this from a completely different process.
     */
    public function toggle(Request $request)
    {
        $enabled = $request->has('backup_enabled');
        SiteSetting::current()->update(['backup_enabled' => $enabled]);

        ActivityLog::create([
            'user_name'  => Auth::user()?->name ?? 'System',
            'activity'   => 'Turned automatic backup ' . ($enabled ? 'on' : 'off'),
            'ip_address' => $request->ip()
        ]);

        return back();
    }

    /**
     * Set backup frequency (daily, weekly, monthly). Persisted the same way
     * as toggle() above, for the same reason.
     */
    public function setFrequency(Request $request)
    {
        $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly',
        ]);

        SiteSetting::current()->update(['backup_frequency' => $request->frequency]);

        ActivityLog::create([
            'user_name'  => Auth::user()?->name ?? 'System',
            'activity'   => 'Changed backup frequency to: ' . $request->frequency,
            'ip_address' => $request->ip()
        ]);

        return back()->with('success', 'Backup frequency updated!');
    }

    /**
     * Delete old backups — keep only the latest MAX_BACKUPS_KEPT
     */
    public function cleanupOldBackups()
    {
        $backupsToDelete = Backup::orderBy('created_at', 'desc')
            ->skip(self::MAX_BACKUPS_KEPT)
            ->take(1000)
            ->get();

        foreach ($backupsToDelete as $backup) {
            if ($disk = $backup->offsiteDisk()) {
                try {
                    $disk->delete($backup->cloud_path);
                    Log::info("Deleted old backup from the '" . ($backup->cloud_disk ?: 'cloud') . "' disk: " . $backup->cloud_path);
                } catch (\Throwable $e) {
                    // Leave the row so the copy is not forgotten; try again next time.
                    Log::warning('Could not delete old off-site backup ' . $backup->cloud_path . ': ' . $e->getMessage());
                    continue;
                }
            }

            if ($backup->local_path && file_exists($backup->local_path)) {
                @unlink($backup->local_path);
                Log::info('Deleted old backup from local storage: ' . $backup->local_path);
            }

            $backup->delete();
        }
    }

    /**
     * Safety net: clean up any leftover temp files if the process dies mid-way
     */
    public function __destruct()
    {
        foreach ($this->tempFiles as $tmp) {
            if (file_exists($tmp)) @unlink($tmp);
        }
    }
}