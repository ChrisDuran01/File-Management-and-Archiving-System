<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use App\Models\ActivityLog;
use App\Models\Backup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    // ─── Tunable constants ────────────────────────────────────────────
    private const CHUNK_SIZE       = 50;    // files processed per batch
    private const DOWNLOAD_TIMEOUT = 180;   // seconds per file download
    private const MAX_RETRIES      = 3;     // retries per file download
    private const RETRY_DELAY_MS   = 2000;  // ms between retries
    private const MAX_BACKUPS_KEPT = 10;    // how many backups to retain
    // ─────────────────────────────────────────────────────────────────

    // Tracks temp files streamed to disk so we can clean up in __destruct
    private array $tempFiles = [];

    /**
     * Create a full backup:
     * 1. List every file on the cloud disk (paginated)
     * 2. Stream each file into a local ZIP (chunked)
     * 3. Upload ZIP back to the cloud disk
     * 4. Save record in database
     */
    public function createBackup()
    {
        // Extend PHP limits for this heavy operation
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $backupFolder = storage_path('app/backups');
        if (!is_dir($backupFolder)) {
            mkdir($backupFolder, 0755, true);
        }

        $fileName = 'backup_' . now()->format('Y_m_d_His') . '.zip';
        $filePath = $backupFolder . DIRECTORY_SEPARATOR . $fileName;

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE) !== true) {
            return back()->with('error', 'Could not create backup ZIP file.');
        }

        // ==============================
        // STEP 1: FETCH FULL FILE LIST (PAGINATED)
        // ==============================

        $files = $this->fetchAllSupabaseFiles();

        if ($files === null) {
            $zip->close();
            @unlink($filePath);
            return back()->with('error', 'Failed to fetch file list from Supabase.');
        }

        if (empty($files)) {
            $zip->close();
            @unlink($filePath);
            return back()->with('error', 'No files found in Supabase bucket.');
        }

        Log::info("Backup started — {$fileName}, total files found: " . count($files));

        // ==============================
        // STEP 2: PROCESS FILES IN CHUNKS
        // ==============================

        $stats  = ['added' => 0, 'skipped' => 0, 'failed' => 0];
        $chunks = array_chunk($files, self::CHUNK_SIZE);

        foreach ($chunks as $chunkIndex => $chunk) {
            Log::info('Processing chunk ' . ($chunkIndex + 1) . ' of ' . count($chunks));

            foreach ($chunk as $file) {
                $name = $file['name'] ?? null;

                if (!$name || $this->shouldSkipFile($name)) {
                    $stats['skipped']++;
                    continue;
                }

                // Stream download directly into ZIP (avoids memory spikes)
                $added = $this->streamFileIntoZip($zip, $name);

                if ($added) {
                    $stats['added']++;
                } else {
                    $stats['failed']++;
                }
            }

            // Give the server a small breath between chunks
            usleep(100000); // 0.1s
        }

        // ==============================
        // STEP 2b: DUMP THE DATABASE INTO THE SAME ZIP
        // ==============================
        // A files-only backup can't rebuild anything - folder/file records,
        // users, activity logs, archives all live in the database. Without
        // this, "restoring from backup" was never actually possible.
        $includesDatabase = $this->addDatabaseDumpToZip($zip);

        $zip->close();

        // Clean up all temp files now that ZIP is finalized
        foreach ($this->tempFiles as $tmp) {
            if (file_exists($tmp)) @unlink($tmp);
        }
        $this->tempFiles = [];

        Log::info("Backup ZIP closed — added: {$stats['added']}, failed: {$stats['failed']}, skipped: {$stats['skipped']}, database: " . ($includesDatabase ? 'yes' : 'no'));

        if ($stats['added'] === 0 && ! $includesDatabase) {
            @unlink($filePath);
            return back()->with('error', 'No files were successfully backed up.');
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
        $cloudPath = 'backups/' . $fileName;

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
            'size'              => $sizeInMB . ' MB',
            'status'            => $uploaded ? 'Success' : 'Local only (cloud upload failed)',
        ]);

        $this->cleanupOldBackups();

        ActivityLog::create([
            'user_name'  => Auth::user()?->name ?? 'System',
            'activity'   => "Created backup: {$fileName} ({$sizeInMB} MB, {$stats['added']} files" . ($includesDatabase ? ', includes database' : ', NO database dump') . ($uploaded ? ', stored locally + cloud' : ', LOCAL ONLY - cloud upload failed') . ')',
            'ip_address' => request()->ip()
        ]);

        $summary = "Backup created! Size: {$sizeInMB} MB | {$stats['added']} files | database " . ($includesDatabase ? 'included' : 'NOT included') . ' | stored ' . ($uploaded ? 'locally and in the cloud' : 'LOCALLY ONLY (cloud upload failed)');
        return back()->with($uploaded && $includesDatabase ? 'success' : 'error', $summary);
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
        $this->tempFiles[] = $sqlPath; // stays alive until $zip->close(), cleaned up after

        return true;
    }

    /**
     * List every file on the cloud disk, paginated.
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
                'prefix' => '',
            ]);

            if (!$response->successful()) {
                Log::error('Failed to list files for backup', [
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $page   = $response->json();
            $all    = array_merge($all, $page);
            $offset += $limit;

            // The list endpoint returns fewer than $limit once we've hit the end
        } while (count($page) === $limit);

        return $all;
    }

    // ─── Read a file from the cloud disk into the ZIP, with retries ───
    private function streamFileIntoZip(ZipArchive $zip, string $path): bool
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;
            $tmpPath = null;

            try {
                $tmpPath = tempnam(sys_get_temp_dir(), 'bkp_');
                $stream  = Storage::disk('cloud')->readStream($path);

                if ($stream === null) {
                    throw new \RuntimeException("Could not open stream for: {$path}");
                }

                // Stream straight to disk — avoids loading the whole file into RAM
                $out = fopen($tmpPath, 'w');
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);

                if (file_exists($tmpPath) && filesize($tmpPath) > 0) {
                    $zipEntryName = basename($path);

                    // addFile() is lazy — temp file must stay alive until zip->close()
                    $zip->addFile($tmpPath, $zipEntryName);
                    $this->tempFiles[] = $tmpPath; // track for cleanup after zip->close()

                    return true;
                }

                @unlink($tmpPath);
                Log::warning("Download attempt {$attempt} failed for: {$path}");

            } catch (\Throwable $e) {
                Log::warning("Exception on attempt {$attempt} for {$path}: " . $e->getMessage());
                if ($tmpPath && file_exists($tmpPath)) @unlink($tmpPath);
            }

            if ($attempt < self::MAX_RETRIES) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }
        }

        Log::error("All {$attempt} attempts failed for: {$path}");
        return false;
    }

    // ─── Upload the final ZIP to the cloud disk ────────────────────────
    private function uploadZipToCloud(string $localPath, string $cloudPath): bool
    {
        $stream = fopen($localPath, 'r');

        try {
            Storage::disk('cloud')->put($cloudPath, $stream);
            return true;
        } catch (\Throwable $e) {
            Log::error('ZIP upload to cloud storage failed: ' . $e->getMessage());
            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
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

        return view('SuperAdmin.backup', [
            'backups'       => $backups,
            'backupEnabled' => session('backup_enabled', true),
            'frequency'     => session('backup_frequency', 'daily'),
        ]);
    }

    /**
     * Enable/disable backups via session toggle
     */
    public function toggle(Request $request)
    {
        $enabled = $request->has('backup_enabled');
        session(['backup_enabled' => $enabled]);

        ActivityLog::create([
            'user_name'  => Auth::user()?->name ?? 'System',
            'activity'   => 'Turned automatic backup ' . ($enabled ? 'on' : 'off'),
            'ip_address' => $request->ip()
        ]);

        return back();
    }

    /**
     * Set backup frequency (daily, weekly, monthly)
     */
    public function setFrequency(Request $request)
    {
        $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly',
        ]);

        session(['backup_frequency' => $request->frequency]);

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