<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ZipArchive;
use App\Models\Backup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BackupController extends Controller
{
    // ─── Tunable constants ────────────────────────────────────────────
    private const CHUNK_SIZE       = 50;    // files processed per batch
    private const SIGNED_URL_TTL   = 3600;  // seconds a signed URL stays valid
    private const DOWNLOAD_TIMEOUT = 180;   // seconds per file download
    private const UPLOAD_TIMEOUT   = 600;   // seconds for final ZIP upload
    private const MAX_RETRIES      = 3;     // retries per file download
    private const RETRY_DELAY_MS   = 2000;  // ms between retries
    private const MAX_BACKUPS_KEPT = 10;    // how many backups to retain
    // ─────────────────────────────────────────────────────────────────

    // Tracks temp files streamed to disk so we can clean up in __destruct
    private array $tempFiles = [];

    /**
     * Create a full backup:
     * 1. Fetch ALL files from Supabase (paginated)
     * 2. Batch-sign URLs, then stream each file into a local ZIP (chunked)
     * 3. Upload ZIP back to Supabase
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

            // Batch-sign all URLs in this chunk in one request
            $signedUrls = $this->batchSignUrls(array_column($chunk, 'name'));

            foreach ($chunk as $file) {
                $name = $file['name'] ?? null;

                if (!$name || $this->shouldSkipFile($name)) {
                    $stats['skipped']++;
                    continue;
                }

                $signedUrl = $signedUrls[$name] ?? null;
                if (!$signedUrl) {
                    Log::warning("No signed URL for: {$name}");
                    $stats['failed']++;
                    continue;
                }

                // Stream download directly into ZIP (avoids memory spikes)
                $added = $this->streamFileIntoZip($zip, $signedUrl, $name);

                if ($added) {
                    $stats['added']++;
                } else {
                    $stats['failed']++;
                }
            }

            // Give the server a small breath between chunks
            usleep(100000); // 0.1s
        }

        $zip->close();

        // Clean up all temp files now that ZIP is finalized
        foreach ($this->tempFiles as $tmp) {
            if (file_exists($tmp)) @unlink($tmp);
        }
        $this->tempFiles = [];

        Log::info("Backup ZIP closed — added: {$stats['added']}, failed: {$stats['failed']}, skipped: {$stats['skipped']}");

        if ($stats['added'] === 0) {
            @unlink($filePath);
            return back()->with('error', 'No files were successfully backed up.');
        }

        // ==============================
        // STEP 3: UPLOAD ZIP TO SUPABASE
        // ==============================

        $sizeInMB  = round(filesize($filePath) / 1024 / 1024, 2);
        $cloudPath = 'backups/' . $fileName;

        $uploaded = $this->uploadZipToSupabase($filePath, $cloudPath);

        // Always clean up the local ZIP after upload attempt
        @unlink($filePath);

        if (!$uploaded) {
            return back()->with('error', 'Backup ZIP created but upload to Supabase failed.');
        }

        // ==============================
        // STEP 4: SAVE RECORD TO DATABASE
        // ==============================

        Backup::create([
            'name'       => $fileName,
            'file_path'  => $cloudPath,
            'cloud_path' => $cloudPath,
            'size'       => $sizeInMB . ' MB',
            'status'     => 'Success',
        ]);

        $this->cleanupOldBackups();

        $summary = "Cloud backup created successfully! Size: {$sizeInMB} MB | {$stats['added']} files backed up | {$stats['failed']} failed";
        return back()->with('success', $summary);
    }

    // ─── Fetch ALL files from Supabase with pagination ────────────────
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
                Log::error('Failed to list Supabase files', [
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $page   = $response->json();
            $all    = array_merge($all, $page);
            $offset += $limit;

            // Supabase returns fewer than $limit when we've hit the end
        } while (count($page) === $limit);

        return $all;
    }

    // ─── Batch-sign multiple URLs in one request ──────────────────────
    private function batchSignUrls(array $names): array
    {
        $bucket = env('SUPABASE_BUCKET');

        // Filter out files we'd skip before signing
        $names = array_values(array_filter($names, fn($n) => !$this->shouldSkipFile($n)));

        if (empty($names)) {
            return [];
        }

        $response = Http::timeout(60)->withHeaders([
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'apikey'        => env('SUPABASE_SERVICE_KEY'),
            'Content-Type'  => 'application/json',
        ])->post(
            env('SUPABASE_URL') . "/storage/v1/object/sign/{$bucket}",
            [
                'paths'     => $names,
                'expiresIn' => self::SIGNED_URL_TTL,
            ]
        );

        if (!$response->successful()) {
            Log::warning('Batch sign failed — falling back to individual signing.');
            return $this->individualSignUrls($names);
        }

        $result = [];
        foreach ($response->json() as $item) {
            if (!empty($item['signedURL']) && !empty($item['path'])) {
                $result[$item['path']] = env('SUPABASE_URL') . '/storage/v1' . $item['signedURL'];
            }
        }

        return $result;
    }

    // ─── Fallback: sign URLs one by one ──────────────────────────────
    private function individualSignUrls(array $names): array
    {
        $bucket = env('SUPABASE_BUCKET');
        $result = [];

        foreach ($names as $name) {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
                'apikey'        => env('SUPABASE_SERVICE_KEY'),
                'Content-Type'  => 'application/json',
            ])->post(
                env('SUPABASE_URL') . "/storage/v1/object/sign/{$bucket}/{$name}",
                ['expiresIn' => self::SIGNED_URL_TTL]
            );

            if ($response->successful() && !empty($response['signedURL'])) {
                $result[$name] = env('SUPABASE_URL') . '/storage/v1' . $response['signedURL'];
            }
        }

        return $result;
    }

    // ─── Stream a remote file to disk then add to ZIP ─────────────────
    private function streamFileIntoZip(ZipArchive $zip, string $url, string $name): bool
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;
            $tmpPath = null;

            try {
                $tmpPath = tempnam(sys_get_temp_dir(), 'bkp_');

                // Stream response body directly to disk — avoids loading into RAM
                $response = Http::timeout(self::DOWNLOAD_TIMEOUT)
                    ->sink($tmpPath)
                    ->get($url);

                if ($response->successful() && file_exists($tmpPath) && filesize($tmpPath) > 0) {
                    $zipEntryName = basename($name);

                    // addFile() is lazy — temp file must stay alive until zip->close()
                    $zip->addFile($tmpPath, $zipEntryName);
                    $this->tempFiles[] = $tmpPath; // track for cleanup after zip->close()

                    return true;
                }

                @unlink($tmpPath);
                Log::warning("Download attempt {$attempt} failed for: {$name}");

            } catch (\Exception $e) {
                Log::warning("Exception on attempt {$attempt} for {$name}: " . $e->getMessage());
                if ($tmpPath && file_exists($tmpPath)) @unlink($tmpPath);
            }

            if ($attempt < self::MAX_RETRIES) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }
        }

        Log::error("All {$attempt} attempts failed for: {$name}");
        return false;
    }

    // ─── Upload the final ZIP to Supabase storage ─────────────────────
    private function uploadZipToSupabase(string $localPath, string $cloudPath): bool
    {
        $bucket    = env('SUPABASE_BUCKET');
        $uploadUrl = env('SUPABASE_URL') . '/storage/v1/object/' . $bucket . '/' . $cloudPath;
        $stream    = fopen($localPath, 'r');

        try {
            $response = Http::timeout(self::UPLOAD_TIMEOUT)
                ->retry(self::MAX_RETRIES, self::RETRY_DELAY_MS)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
                    'apikey'        => env('SUPABASE_SERVICE_KEY'),
                    'x-upsert'      => 'true',
                    'Content-Type'  => 'application/zip',
                ])
                ->send('PUT', $uploadUrl, ['body' => $stream]);

            fclose($stream);

            if (!$response->successful()) {
                Log::error('ZIP upload to Supabase failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            fclose($stream);
            Log::error('ZIP upload exception: ' . $e->getMessage());
            return false;
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
     * Download backup file using a signed URL
     */
    public function download($id)
    {
        $backup = Backup::findOrFail($id);
        $bucket = env('SUPABASE_BUCKET');

        // Generate signed URL
        $signedUrlResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'apikey'        => env('SUPABASE_SERVICE_KEY'),
        ])->post(
            env('SUPABASE_URL') . "/storage/v1/object/sign/$bucket/" . $backup->file_path,
            ['expiresIn' => 600]
        );

        if (!$signedUrlResponse->successful()) {
            return back()->with('error', 'Failed to generate download link.');
        }

        $signedUrl = env('SUPABASE_URL') . '/storage/v1' . $signedUrlResponse['signedURL'];

        try {
            $content = file_get_contents($signedUrl);

            return response($content)
                ->header('Content-Type', 'application/zip')
                ->header('Content-Disposition', 'attachment; filename="' . $backup->name . '"');

        } catch (\Exception $e) {
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
            if ($backup->file_path) {
                $deleteUrl = env('SUPABASE_URL')
                    . '/storage/v1/object/'
                    . env('SUPABASE_BUCKET')
                    . '/' . $backup->cloud_path;

                $response = Http::withHeaders([
                    'apikey'        => env('SUPABASE_SERVICE_KEY'),
                    'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
                ])->delete($deleteUrl);

                Log::info('Supabase delete', [
                    'path'     => $backup->file_path,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
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