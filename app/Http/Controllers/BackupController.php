<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ZipArchive;
use App\Models\Backup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BackupController extends Controller
{

    /**
     * Create a full backup:
     * 1. Fetch files from Supabase
     * 2. Zip them locally
     * 3. Upload zip back to Supabase
     * 4. Save record in database
     */
    public function createBackup()
    {
        // Local backup folder
        $backupFolder = storage_path('app/backups');

        // Create folder if it doesn't exist
        if (!file_exists($backupFolder)) {
            mkdir($backupFolder, 0777, true);
        }

        // Generate backup filename
        $fileName = 'backup_' . now()->format('Y_m_d_His') . '.zip';
        $filePath = $backupFolder . DIRECTORY_SEPARATOR . $fileName;

        $zip = new ZipArchive;

        // Try to create ZIP file
        if ($zip->open($filePath, ZipArchive::CREATE) !== TRUE) {
            return back()->with('error', 'Could not create backup zip.');
        }

        $addedAnyFile = false;

        // ==============================
        // STEP 1: FETCH FILE LIST FROM SUPABASE
        // ==============================

        $bucket = env('SUPABASE_BUCKET');

        $listUrl = env('SUPABASE_URL') . '/storage/v1/object/list/' . $bucket;

        $listResponse = Http::timeout(300)->withHeaders([
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'apikey' => env('SUPABASE_SERVICE_KEY'),
        ])->post($listUrl, [
            'limit' => 1000,
            'offset' => 0,
            'prefix' => ''
        ]);

        // Stop if request failed
        if (!$listResponse->successful()) {
            return back()->with('error', 'Failed to fetch Supabase files.');
        }

        $files = $listResponse->json();

        // ==============================
        // STEP 2: DOWNLOAD FILES + ADD TO ZIP
        // ==============================

        foreach ($files as $file) {

            // Skip if invalid file structure
            if (!isset($file['name'])) continue;

            $fileNameOnly = $file['name'];

            // Skip old backup ZIP files
            if (
                str_starts_with($fileNameOnly, 'backup_') &&
                str_ends_with($fileNameOnly, '.zip')
            ) {
                continue;
            }

            // Skip files inside backup folder
            if (str_starts_with($fileNameOnly, 'backups/')) {
                continue;
            }

            // Generate signed URL (for private file access)
            $signedUrlResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
                'apikey' => env('SUPABASE_SERVICE_KEY'),
                'Content-Type' => 'application/json'
            ])->post(
                env('SUPABASE_URL') . "/storage/v1/object/sign/$bucket/$fileNameOnly",
                [
                    'expiresIn' => 600 // URL valid for 10 minutes
                ]
            );

            // Skip if signing fails
            if (!$signedUrlResponse->successful()) {
                continue;
            }

            // Construct full signed URL
            $signedUrl = env('SUPABASE_URL') .
                '/storage/v1' .
                $signedUrlResponse['signedURL'];

            try {

                // Download file content
                $response = Http::timeout(120)->get($signedUrl);

                if ($response->successful()) {

                    // Add file into ZIP
                    $zip->addFromString(
                        basename($fileNameOnly),
                        $response->body()
                    );

                    $addedAnyFile = true;
                }

            } catch (\Exception $e) {
                // Log failure but continue process
                Log::warning('Failed to download file: ' . $fileNameOnly);
            }
        }

        // Finalize ZIP
        $zip->close();

        // If nothing was added, delete empty ZIP
        if (!$addedAnyFile || !file_exists($filePath)) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return back()->with('error', 'No valid cloud files were backed up.');
        }

        // Calculate file size in MB
        $sizeInMB = round(filesize($filePath) / 1024 / 1024, 2);

        // ==============================
        // STEP 3: UPLOAD ZIP TO SUPABASE
        // ==============================

        $cloudPath = 'backups/' . $fileName;

        $uploadUrl = env('SUPABASE_URL') . '/storage/v1/object/' .
            $bucket . '/' . $cloudPath;

        // Open file stream for upload
        $stream = fopen($filePath, 'r');

        $uploadResponse = Http::timeout(300)
            ->retry(3, 2000) // retry 3 times if failed
            ->withHeaders([
                'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
                'apikey' => env('SUPABASE_SERVICE_KEY'),
                'x-upsert' => 'true', // overwrite if exists
                'Content-Type' => 'application/zip',
            ])
            ->send('PUT', $uploadUrl, [
                'body' => $stream,
            ]);

        fclose($stream);

        // Handle upload failure
        if (!$uploadResponse->successful()) {
            Log::error('Backup upload failed', [
                'status' => $uploadResponse->status(),
                'body' => $uploadResponse->body(),
            ]);

            return back()->with('error', 'Backup created but upload failed.');
        }

        // ==============================
        // STEP 4: SAVE RECORD TO DATABASE
        // ==============================

        Backup::create([
            'name' => $fileName,
            'file_path' => $cloudPath,
            'cloud_path' => $cloudPath,
            'size' => $sizeInMB . ' MB',
            'status' => 'Success'
        ]);

        // Clean old backups
        $this->cleanupOldBackups();

        return back()->with('success', "Cloud backup created successfully! Size: {$sizeInMB} MB");
    }

    /**
     * Download backup file using signed URL
     */
    public function download($id)
    {
        $backup = Backup::findOrFail($id);
        $bucket = env('SUPABASE_BUCKET');

        // Generate signed URL
        $signedUrlResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'apikey' => env('SUPABASE_SERVICE_KEY'),
        ])->post(
            env('SUPABASE_URL') . "/storage/v1/object/sign/$bucket/" . $backup->file_path,
            [
                'expiresIn' => 600
            ]
        );

        if (!$signedUrlResponse->successful()) {
            return back()->with('error', 'Failed to generate download link.');
        }

        $signedUrl = env('SUPABASE_URL') .
            '/storage/v1' .
            $signedUrlResponse['signedURL'];

        try {
            // Fetch file contents
            $content = file_get_contents($signedUrl);

            // Return as downloadable response
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
            'backups' => $backups,
            'backupEnabled' => session('backup_enabled', true),
            'frequency' => session('backup_frequency', 'daily'),
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
     * Delete old backups (keep latest 10)
     */
    public function cleanupOldBackups()
    {
        $backupsToDelete = Backup::orderBy('created_at', 'desc')
                                ->skip(10)
                                ->take(1000)
                                ->get();

        foreach ($backupsToDelete as $backup) {

            if ($backup->file_path) {

                $path = $backup->file_path;

                // Delete file from Supabase
                $response = Http::withHeaders([
                    'apikey' => env('SUPABASE_SERVICE_KEY'),
                    'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
                ])->delete(
                    env('SUPABASE_URL') . '/storage/v1/object/' . $path
                );

                Log::info('Supabase delete', [
                    'path' => $path,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }

            // Delete DB record
            $backup->delete();
        }
    }
}