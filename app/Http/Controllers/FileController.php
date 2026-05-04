<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{

    /**
     * Handle file upload
     * - Saves file locally
     * - Uploads file to Supabase
     * - Stores metadata in database
     */
    public function store(Request $request)
    {
        // Validate incoming files (max 40MB each)
        $request->validate([
            'files.*'   => 'file|max:40960',
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        // Supabase configuration
        $bucket = env('SUPABASE_BUCKET');
        $url    = env('SUPABASE_URL');
        $key    = env('SUPABASE_SERVICE_KEY');

        // Ensure files exist in request
        if (! $request->hasFile('files')) {
            return back()->with('error', 'No file selected');
        }

        // Loop through each uploaded file
        foreach ($request->file('files') as $uploadedFile) {

            // =============================
            // STEP 1: HANDLE FILE NAMING
            // =============================

            // Get original file details
            $originalName = $uploadedFile->getClientOriginalName();
            $name         = pathinfo($originalName, PATHINFO_FILENAME);
            $extension    = $uploadedFile->getClientOriginalExtension();

            // Prevent duplicate filenames (per folder)
            $count       = 0;
            $newFileName = $originalName;

            while (
                File::where('filename', $newFileName)
                ->where(function ($q) use ($request) {
                    // Check within same folder OR root
                    if ($request->folder_id) {
                        $q->where('folder_id', $request->folder_id);
                    } else {
                        $q->whereNull('folder_id');
                    }
                })
                ->exists()
            ) {
                $count++;
                $newFileName = $name . " ($count)." . $extension;
            }

            // Generate unique storage filename for Supabase
            $filePath = time() . '_' . Str::slug($name) . '_' . $count . '.' . $extension;

            // =============================
            // STEP 2: SAVE FILE LOCALLY
            // =============================

            // Save file in storage/app/public/uploads/{folder or root}
            $localPath = $uploadedFile->store(
                'uploads/' . ($request->folder_id ?? 'root'),
                'public'
            );

            // Alternative manual save (if needed)
            // Storage::disk('public')->put($localPath, file_get_contents($uploadedFile->getRealPath()));

            // =============================
            // STEP 3: UPLOAD TO SUPABASE
            // =============================

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'apikey' => $key,
            ])->attach(
                'file',
                file_get_contents($uploadedFile->getRealPath()),
                $filePath
            )->post("$url/storage/v1/object/$bucket/$filePath");

            // If upload fails:
            if (!$response->successful()) {

                // Remove local file to avoid inconsistency
                Storage::disk('public')->delete($localPath);

                // Debug response (you may replace with logging in production)
                dd([
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }

            // =============================
            // STEP 4: SAVE FILE RECORD DATABSE
            // =============================

            $file = File::create([
                'filename'     => $newFileName,     // user-visible name
                'filepath'     => $filePath,        // Supabase path
                'local_path'   => $localPath,       // local storage path
                'folder_id'    => $request->folder_id ?? null,
                'size'         => $uploadedFile->getSize(),
                'storage_type' => 'both',           // local + cloud
            ]);

            // =============================
            // STEP 5: LOG ACTIVITY DATABASE
            // =============================

            ActivityLog::create([
                'user_name' => Auth::user()->name,
                'activity'  => 'Uploaded file "' . $file->filename . '"',
                'ip_address' => $request->ip()
            ]);
        }

        return back()->with('success', 'Files uploaded successfully!');
    }


    /**
     * Preview file using a signed Supabase URL
     */
    public function preview($id)
    {
        // Find file or fail
        $file = File::findOrFail($id);

        // Supabase configuration
        $bucket = env('SUPABASE_BUCKET');
        $url    = env('SUPABASE_URL');
        $key    = env('SUPABASE_SERVICE_KEY');

        // Request signed URL for private file access
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $key,
            'apikey'        => $key,
            'Content-Type'  => 'application/json',
        ])->post("$url/storage/v1/object/sign/$bucket/" . $file->filepath, [
            'expiresIn' => 3600, // 1 hour validity
        ]);

        // Handle failure
        if (! $response->successful()) {
            return back()->with('error', 'Failed to generate preview URL');
        }

        // Build full signed URL
        $signedUrl = $url . '/storage/v1' . $response['signedURL'];

        // Send to preview view
        return view('Admin.preview', compact('file', 'signedUrl'));
    }


public function download($id)
{
    $file = File::findOrFail($id);

    $bucket = env('SUPABASE_BUCKET');
    $url    = env('SUPABASE_URL');
    $key    = env('SUPABASE_SERVICE_KEY');

    // Generate signed URL
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $key,
        'apikey'        => $key,
    ])->post("$url/storage/v1/object/sign/$bucket/{$file->filepath}", [
        'expiresIn' => 3600,
    ]);

    if (!$response->successful()) {
        return back()->with('error', 'Download failed');
    }

    $signedUrl = $url . '/storage/v1' . $response['signedURL'];

    // Fetch file contents from Supabase
    $fileResponse = Http::get($signedUrl);

    if (!$fileResponse->successful()) {
        return back()->with('error', 'Unable to fetch file');
    }

    // Force download
    return response()->streamDownload(function () use ($fileResponse) {
        echo $fileResponse->body();
    }, basename($file->filepath));
}

    public function toggleAccess($id, Request $request)
{
    $file = File::findOrFail($id);

    $file->is_public = $file->is_public == 1 ? 0 : 1;
    $file->save();

    // Super Admin / Normal User
        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Changed access for file: ' . $file->filename . ' to ' . ($file->is_public ? 'public' : 'private'),
            'ip_address' => $request->ip()
        ]);

    return back()->with('success', 'File access updated successfully.');
}

public function destroy($id)
{
    $file = File::findOrFail($id);

    $bucket = env('SUPABASE_BUCKET');
    $url    = env('SUPABASE_URL');
    $key    = env('SUPABASE_SERVICE_KEY');

    // 1. Delete file from Supabase Storage
    Http::withHeaders([
        'Authorization' => 'Bearer ' . $key,
        'apikey'        => $key,
    ])->delete("$url/storage/v1/object/$bucket/{$file->filepath}");

    // 2. Delete from database
    $file->delete();

    // Log activity  
     ActivityLog::create([
        'user_name' => Auth::user()->name,
        'activity' => 'Deleted file: ' . $file->filename,
        'ip_address' => request()->ip()
    ]);

    return back()->with('success', 'File deleted successfully.');
}


public function rename(Request $request, $id)
{
    $file = File::findOrFail($id);

    $request->validate([
        'new_name' => 'required|string|max:255',
    ]);

    $bucket = env('SUPABASE_BUCKET');
    $url    = env('SUPABASE_URL');
    $key    = env('SUPABASE_SERVICE_KEY');

    $oldPath = ltrim($file->filepath, './'); // prevent "." folder issue

    $pathParts = pathinfo($oldPath);

    // =========================
    // SAFE DIRECTORY HANDLING
    // =========================
    $dirname = ($pathParts['dirname'] === '.' || $pathParts['dirname'] === '')
        ? null
        : $pathParts['dirname'];

    // =========================
    // KEEP FILE EXTENSION
    // =========================
    $extension = $pathParts['extension'] ?? '';

    // =========================
    // SANITIZE FILE NAME
    // =========================
    $cleanName = preg_replace('/[^A-Za-z0-9 _-]/', '', $request->new_name);
    $cleanName = trim($cleanName);
    $cleanName = str_replace(' ', '_', $cleanName);

    // =========================
    // BUILD NEW FILE NAME
    // =========================
    $newFileName = $extension
        ? $cleanName . '.' . $extension
        : $cleanName;

    // =========================
    // BUILD SAFE NEW PATH
    // =========================
    $newPath = $dirname
        ? $dirname . '/' . $newFileName
        : $newFileName;

    // =========================
    // MOVE FILE IN SUPABASE
    // =========================
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $key,
        'apikey'        => $key,
    ])->post("$url/storage/v1/object/move", [
        'bucketId'       => $bucket,
        'sourceKey'      => $oldPath,
        'destinationKey' => $newPath,
    ]);

    if (!$response->successful()) {
        return back()->with('error', 'Failed to rename file in storage.');
    }

    // =========================
    // UPDATE DATABASE
    // =========================
    $file->filename = $newFileName;
    $file->filepath = $newPath;
    $file->save();

    // =========================
    // LOG ACTIVITY
    // =========================
    ActivityLog::create([
        'user_name'  => Auth::user()->name,
        'activity'   => 'Renamed file to: ' . $file->filename,
        'ip_address' => $request->ip()
    ]);

    return back()->with('success', 'File renamed successfully.');
}
}