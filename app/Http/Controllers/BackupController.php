<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use App\Jobs\RunBackupJob;
use App\Models\ActivityLog;
use App\Models\Backup;
use App\Models\File;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\BackupFailed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Auth;
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
            $files = $this->fetchAllSupabaseFiles();

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

            Log::info("Backup ZIP closed — added: {$stats['added']}, failed: {$stats['failed']}, skipped: {$stats['skipped']}, database: " . ($includesDatabase ? 'yes' : 'no'));

            if ($stats['added'] === 0 && ! $includesDatabase) {
                @unlink($filePath);
                $this->setProgress('error', 100, 'No files were successfully backed up.');
                return;
            }

            // ==============================
            // STEP 3: UPLOAD TO THE CLOUD DISK, KEEP A LOCAL COPY TOO
            // ==============================
            // Redundancy means two independent locations: previously the local
            // ZIP was deleted right after upload, leaving only one copy - in the
            // same cloud bucket the live files sit in. Losing that bucket (or
            // the Supabase account) lost every backup along with the originals.
            // Keeping the local copy means either one alone can still recover.

            $sizeInMB  = round(filesize($filePath) / 1024 / 1024, 2);
            $checksum  = hash_file('sha256', $filePath);
            $cloudPath = 'backups/' . $fileName;

            $this->setProgress('uploading', 70, 'Uploading backup to cloud storage…');
            $uploaded = $this->uploadZipToCloud($filePath, $cloudPath);

            if (! $uploaded) {
                Log::warning('Backup upload to cloud storage failed - keeping the local copy only.');
            }

            // ==============================
            // STEP 4: SAVE RECORD TO DATABASE
            // ==============================

            Backup::create([
                'name'              => $fileName,
                'file_path'         => $cloudPath,
                'cloud_path'        => $uploaded ? $cloudPath : null,
                'local_path'        => $filePath,
                'includes_database' => $includesDatabase,
                'checksum'          => $checksum,
                'size'              => $sizeInMB . ' MB',
                'status'            => $uploaded ? 'Success' : 'Local only (cloud upload failed)',
            ]);

            $this->cleanupOldBackups();

            ActivityLog::create([
                'user_name'  => $initiatedBy ?? 'System',
                'activity'   => "Created backup: {$fileName} ({$sizeInMB} MB, {$stats['added']} files" . ($includesDatabase ? ', includes database' : ', NO database dump') . ($uploaded ? ', stored locally + cloud' : ', LOCAL ONLY - cloud upload failed') . ')',
                'ip_address' => $initiatedByIp
            ]);

            $summary = "Backup created! Size: {$sizeInMB} MB | {$stats['added']} files | database " . ($includesDatabase ? 'included' : 'NOT included') . ' | stored ' . ($uploaded ? 'locally and in the cloud' : 'LOCALLY ONLY (cloud upload failed)');

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

    /**
     * Dumps the full database via mysqldump and adds it to the ZIP as
     * database_backup.sql. Failure here doesn't abort the whole backup - a
     * files-only backup is still better than none - but is reported clearly
     * so it's never mistaken for a full one.
     */
    private function addDatabaseDumpToZip(ZipArchive $zip): bool
    {
        $sqlPath = tempnam(sys_get_temp_dir(), 'dbdump_') . '.sql';

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

        $zip->addFile($sqlPath, 'database_backup.sql');
        $zip->setEncryptionName('database_backup.sql', ZipArchive::EM_AES_256);
        $this->applyFastCompression($zip, 'database_backup.sql');
        $this->tempFiles[] = $sqlPath; // stays alive until $zip->close(), cleaned up after

        return true;
    }

    /**
     * List every real file on the cloud disk, at any depth, paginated.
     *
     * This is the one place that still talks to Supabase's REST API directly
     * instead of going through Storage::disk('cloud'): the installed Flysystem
     * adapter's listContents() hardcodes a 100-item page with no way for
     * callers to page past it, which would silently truncate backups on any
     * bucket with more than 100 objects. Every other operation in this class
     * goes through the disk abstraction - if a future provider's adapter
     * paginates properly, this method is the only one that needs replacing.
     */
    private function fetchAllSupabaseFiles(): ?array
    {
        return $this->fetchFilesAtPrefix('');
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

        $bucket  = env('SUPABASE_BUCKET');
        $listUrl = env('SUPABASE_URL') . '/storage/v1/object/list/' . $bucket;

        do {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
                'apikey'        => env('SUPABASE_SERVICE_KEY'),
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
                continue; // no metadata to rename with - fulfilled() falls back to $name
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
     * Talks to the Supabase Storage REST API directly (same endpoint the
     * Flysystem adapter's readStream() hits) since Guzzle's Pool needs
     * request-level control the Storage facade doesn't expose.
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

        $bucket = env('SUPABASE_BUCKET');

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

        $client = new GuzzleClient([
            'base_uri' => rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/',
            'handler'  => $stack,
            'timeout'  => self::DOWNLOAD_TIMEOUT,
            'headers'  => [
                'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
                'apikey'        => env('SUPABASE_SERVICE_KEY'),
            ],
        ]);

        $tempPaths = [];
        foreach ($toFetch as $name) {
            $tempPaths[$name] = tempnam(sys_get_temp_dir(), 'bkp_');
        }

        $requests = function () use ($client, $bucket, $toFetch, $tempPaths) {
            foreach ($toFetch as $name) {
                $tmpPath = $tempPaths[$name];
                yield $name => function (array $opts) use ($client, $bucket, $name, $tmpPath) {
                    return $client->getAsync('object/' . $bucket . '/' . $name, array_merge($opts, [
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
    private function uploadZipToCloud(string $localPath, string $cloudPath): bool
    {
        $bucket = env('SUPABASE_BUCKET');
        $url    = rtrim(env('SUPABASE_URL'), '/') . '/storage/v1/object/' . $bucket . '/' . ltrim($cloudPath, '/');

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $handle = fopen($localPath, 'r');

            if ($handle === false) {
                Log::error('Could not open backup ZIP for upload: ' . $localPath);
                return false;
            }

            $lastProgressAt = 0.0;

            try {
                $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
                        'apikey'        => env('SUPABASE_SERVICE_KEY'),
                        'x-upsert'      => 'true',
                    ])
                    ->withBody($handle, 'application/zip')
                    ->withOptions([
                        'timeout'  => self::UPLOAD_TIMEOUT,
                        'progress' => function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) use (&$lastProgressAt) {
                            if ($uploadTotal <= 0) {
                                return;
                            }

                            $now = microtime(true);
                            if ($now - $lastProgressAt < 0.3 && $uploadedBytes < $uploadTotal) {
                                return;
                            }
                            $lastProgressAt = $now;

                            $percent = 70 + (int) round(($uploadedBytes / $uploadTotal) * 29);
                            $this->setProgress(
                                'uploading',
                                min(99, $percent),
                                'Uploading backup to cloud storage… (' . $this->formatBytes($uploadedBytes) . ' / ' . $this->formatBytes($uploadTotal) . ')'
                            );
                        },
                    ])
                    ->post($url);

                if ($response->successful() && $response->json('Id') !== null) {
                    return true;
                }

                Log::warning("Backup upload attempt {$attempt} failed: HTTP {$response->status()} — " . $response->body());

            } catch (\Throwable $e) {
                Log::warning("Backup upload attempt {$attempt} threw: " . $e->getMessage());
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }

            if ($attempt < self::MAX_RETRIES) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }
        }

        Log::error('All backup upload attempts to cloud storage failed.');
        return false;
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

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
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
            // Prefer the local copy - faster, and still works if the cloud
            // account/bucket is unreachable, which is the whole point of
            // keeping two independent copies.
            if ($backup->local_path && file_exists($backup->local_path)) {
                $content = file_get_contents($backup->local_path);
            } else {
                $content = Storage::disk('cloud')->get($backup->cloud_path ?? $backup->file_path);
            }

            ActivityLog::create([
                'user_name'  => Auth::user()?->name ?? 'System',
                'activity'   => 'Downloaded backup: ' . $backup->name,
                'ip_address' => request()->ip()
            ]);

            return response($content)
                ->header('Content-Type', 'application/zip')
                ->header('Content-Disposition', 'attachment; filename="' . $backup->name . '"');

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
            if ($backup->cloud_path) {
                Storage::disk('cloud')->delete($backup->cloud_path);
                Log::info('Deleted old backup from cloud storage: ' . $backup->cloud_path);
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