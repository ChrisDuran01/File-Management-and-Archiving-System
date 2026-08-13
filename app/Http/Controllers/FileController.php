<?php
namespace App\Http\Controllers;

use App\Jobs\CacheFileLocally;
use App\Jobs\ExtractFileOcrText;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Services\CloudFileUploader;
use App\Services\SupabaseSignedUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Whitelisted document/image types accepted through this upload form.
     * Laravel's `mimes` rule doesn't trust the client-supplied extension - it
     * sniffs the file's actual content (magic bytes via fileinfo) and only
     * passes if the real content matches one of these types, so a script
     * renamed to look like a document still gets rejected.
     */
    private const ALLOWED_MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,webp';

    /**
     * Handle file upload
     * - Saves files locally
     * - Uploads every file to the cloud disk, then stores metadata in the
     *   database via a single bulk insert.
     */
    public function store(Request $request, CloudFileUploader $uploader)
    {
        set_time_limit(0);

        // Validate incoming files (max 1GB each, matches php.ini upload_max_filesize)
        $request->validate([
            'files.*'   => 'file|max:1048576|mimes:' . self::ALLOWED_MIMES,
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        // Ensure files exist in request
        if (! $request->hasFile('files')) {
            return back()->with('error', 'No file selected');
        }

        // Folders stored under a past school year are read-only
        if ($request->folder_id && Folder::find($request->folder_id)?->is_archived) {
            return back()->with('error', 'This folder is stored under a past school year and is read-only.');
        }

        // Fetch existing filenames once instead of running a duplicate-check
        // query per file
        $existingNames = File::where(function ($q) use ($request) {
                if ($request->folder_id) {
                    $q->where('folder_id', $request->folder_id);
                } else {
                    $q->whereNull('folder_id');
                }
            })
            ->pluck('filename')
            ->flip()
            ->all();

        // =============================
        // STEP 1: NAME + SAVE EVERY FILE LOCALLY FIRST (fast, local disk)
        // =============================
        $prepared = [];

        foreach ($request->file('files') as $uploadedFile) {
            $originalName = $uploadedFile->getClientOriginalName();
            $name         = pathinfo($originalName, PATHINFO_FILENAME);
            $extension    = $uploadedFile->getClientOriginalExtension();

            $count       = 0;
            $newFileName = $originalName;

            while (isset($existingNames[$newFileName])) {
                $count++;
                $newFileName = $name . " ($count)." . $extension;
            }
            $existingNames[$newFileName] = true;

            $filePath  = time() . '_' . Str::slug($name) . '_' . $count . '_' . uniqid() . '.' . $extension;
            $localPath = $uploadedFile->store('uploads/' . ($request->folder_id ?? 'root'), 'public');

            $prepared[] = [
                'newFileName' => $newFileName,
                'filePath'    => $filePath,
                'localPath'   => $localPath,
                'fullPath'    => Storage::disk('public')->path($localPath),
                'size'        => $uploadedFile->getSize(),
            ];
        }

        // =============================
        // STEP 2: UPLOAD EACH FILE TO THE CLOUD DISK
        // =============================
        // A cloud failure no longer throws the file away - the local copy
        // from Step 1 already saved fine, so it's kept and marked
        // 'local' instead of 'both'. files:sync-to-cloud picks these up
        // later and finishes the job once the connection recovers.
        $rows           = [];
        $localOnlyNames = [];
        $now            = now();

        foreach ($prepared as $item) {
            $uploadedToCloud = $uploader->upload($item['filePath'], $item['fullPath']);

            if (! $uploadedToCloud) {
                $localOnlyNames[] = $item['newFileName'];
            }

            $rows[] = [
                'filename'     => $item['newFileName'],
                'filepath'     => $item['filePath'],
                'local_path'   => $item['localPath'],
                'folder_id'    => $request->folder_id ?? null,
                'size'         => $item['size'],
                'storage_type' => $uploadedToCloud ? 'both' : 'local',
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        // =============================
        // STEP 3: BULK INSERT + ONE ACTIVITY LOG FOR THE WHOLE BATCH
        // =============================
        if ($rows) {
            File::insert($rows);

            // Bulk insert doesn't return IDs, so look the new rows back up by
            // the timestamp this whole batch shares to queue OCR per file.
            $scanAsDocument = $request->boolean('scan_as_document');

            File::whereIn('filename', collect($rows)->pluck('filename'))
                ->where('created_at', $now)
                ->pluck('id')
                ->each(fn ($id) => ExtractFileOcrText::dispatch($id, $scanAsDocument));

            ActivityLog::create([
                'user_name'  => Auth::user()->name,
                'activity'   => 'Uploaded ' . count($rows) . ' file(s): ' . collect($rows)->pluck('filename')->implode(', '),
                'ip_address' => $request->ip(),
            ]);
        }

        if ($localOnlyNames) {
            $count = count($localOnlyNames);

            return back()->with('success',
                count($rows) . ' file(s) uploaded. ' . $count . ' ' . ($count === 1 ? 'couldn\'t' : 'couldn\'t')
                . ' reach cloud storage right now and ' . ($count === 1 ? 'was' : 'were')
                . ' saved locally instead - still fully usable, and will sync to the cloud automatically'
                . ' once the connection recovers: ' . implode(', ', $localOnlyNames)
            );
        }

        return back()->with('success', 'Files uploaded successfully!');
    }

    /**
     * Step 1 of the direct-to-cloud upload flow: resolves each filename
     * against existing names in the target folder (same dedup logic as
     * store()'s STEP 1, run once for the whole batch so two files sharing a
     * name in the same batch don't race each other) and returns a signed
     * Supabase upload URL for each. The browser PUTs the actual bytes
     * straight to that URL - this endpoint never touches file contents.
     */
    public function prepareUpload(Request $request, SupabaseSignedUpload $signer)
    {
        $request->validate([
            'filenames'   => 'required|array|min:1',
            'filenames.*' => 'string',
            'folder_id'   => 'nullable|exists:folders,id',
        ]);

        if ($request->folder_id && Folder::find($request->folder_id)?->is_archived) {
            return response()->json(['error' => 'This folder is stored under a past school year and is read-only.'], 422);
        }

        $existingNames = File::where(function ($q) use ($request) {
                if ($request->folder_id) {
                    $q->where('folder_id', $request->folder_id);
                } else {
                    $q->whereNull('folder_id');
                }
            })
            ->pluck('filename')
            ->flip()
            ->all();

        $results = [];

        foreach ($request->filenames as $originalName) {
            $name      = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);

            $count       = 0;
            $newFileName = $originalName;

            while (isset($existingNames[$newFileName])) {
                $count++;
                $newFileName = $name . " ($count)." . $extension;
            }
            $existingNames[$newFileName] = true;

            $cloudPath = time() . '_' . Str::slug($name) . '_' . $count . '_' . uniqid() . '.' . $extension;
            $uploadUrl = $signer->createUrl($cloudPath);

            $results[] = [
                'originalName' => $originalName,
                'newFileName'  => $uploadUrl ? $newFileName : null,
                'cloudPath'    => $uploadUrl ? $cloudPath : null,
                'uploadUrl'    => $uploadUrl,
            ];
        }

        return response()->json(['files' => $results]);
    }

    /**
     * Step 2 of the direct-to-cloud upload flow: called by the browser only
     * after its direct PUT to Supabase (from prepareUpload()'s signed URL)
     * has already succeeded - this just records the metadata. No file
     * contents ever pass through this server for files uploaded this way.
     */
    public function confirmUpload(Request $request)
    {
        $request->validate([
            'filename'  => 'required|string',
            'cloudPath' => 'required|string',
            'folder_id' => 'nullable|exists:folders,id',
            'size'      => 'required|integer',
        ]);

        // 'cloud' (not 'both') until CacheFileLocally actually lands a local
        // copy - marking it 'both' immediately would claim a redundant local
        // copy that doesn't exist yet.
        $file = File::create([
            'filename'     => $request->filename,
            'filepath'     => $request->cloudPath,
            'local_path'   => null,
            'folder_id'    => $request->folder_id,
            'size'         => $request->size,
            'storage_type' => 'cloud',
        ]);

        ExtractFileOcrText::dispatch($file->id, $request->boolean('scan_as_document'));
        CacheFileLocally::dispatch($file->id);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Uploaded file: ' . $file->filename,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['id' => $file->id, 'filename' => $file->filename]);
    }

    /**
     * A local-only file (its cloud upload never completed) has no cloud
     * object to sign a URL for - serve straight from the local disk's
     * public URL instead. Also falls back to local for a 'both' file if the
     * cloud call itself fails, so a temporary Supabase outage doesn't take
     * previews down for files that already have a perfectly good local copy.
     */
    private function resolvePreviewUrl(File $file): ?string
    {
        if ($file->storage_type !== 'local') {
            try {
                return Storage::disk('cloud')->getAdapter()->getSignedUrl($file->filepath, ['expiresIn' => 3600]);
            } catch (\Throwable $e) {
                Log::warning("Cloud signed URL failed for file {$file->id}, falling back to local: " . $e->getMessage());
            }
        }

        return $file->local_path && Storage::disk('public')->exists($file->local_path)
            ? Storage::disk('public')->url($file->local_path)
            : null;
    }

    /**
     * Same local-first fallback as resolvePreviewUrl(), but returning raw
     * bytes - used for text preview and for download.
     */
    private function resolveFileContents(File $file): ?string
    {
        if ($file->storage_type !== 'local') {
            try {
                return Storage::disk('cloud')->get($file->filepath);
            } catch (\Throwable $e) {
                Log::warning("Cloud read failed for file {$file->id}, falling back to local: " . $e->getMessage());
            }
        }

        if ($file->local_path && Storage::disk('public')->exists($file->local_path)) {
            return Storage::disk('public')->get($file->local_path);
        }

        return null;
    }

    /**
     * Preview file using a signed cloud-storage URL, falling back to the
     * local disk when the cloud copy isn't there or isn't reachable.
     */
    public function preview($id)
    {
        $file = File::findOrFail($id);

        if (! $file->isAccessibleBy(Auth::user())) {
            abort(403, 'This file is in a restricted folder you don\'t have access to.');
        }

        $file->touchAccessed();

        $signedUrl = $this->resolvePreviewUrl($file);

        if (! $signedUrl) {
            abort(500, 'Failed to generate preview URL');
        }

        $ext = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));

        $isImage  = in_array($ext, ['jpg','jpeg','png','gif','webp','svg']);
        $isPdf    = $ext === 'pdf';
        $isVideo  = in_array($ext, ['mp4','mov','webm','avi']);
        $isAudio  = in_array($ext, ['mp3','wav','ogg','m4a']);
        $isText   = in_array($ext, ['txt','md','csv','log','json','xml','html','css','js','php']);

        // TEXT CONTENT
        $textContent = null;

        if ($isText) {
            $contents = $this->resolveFileContents($file);
            $textContent = $contents !== null ? substr($contents, 0, 50000) : 'Unable to load file.';
        }

        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.preview', compact(
            'file','signedUrl','ext',
            'isImage','isPdf','isVideo','isAudio','isText',
            'textContent','layout'
        ));
    }

    public function download($id)
    {
        $file = File::findOrFail($id);

        if (! $file->isAccessibleBy(Auth::user())) {
            abort(403, 'This file is in a restricted folder you don\'t have access to.');
        }

        $file->touchAccessed();

        $contents = $this->resolveFileContents($file);

        if ($contents === null) {
            return back()->with('error', 'Unable to fetch file');
        }

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Downloaded file: ' . $file->filename,
            'ip_address' => request()->ip()
        ]);

        // Force download
        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, basename($file->filepath));
    }

    public function toggleAccess($id, Request $request)
{
    $file = File::findOrFail($id);

    if (! $file->isManageableBy(Auth::user())) {
        return back()->with('error', 'Only this file\'s folder creator or a SuperAdmin can change its access.');
    }

    if ($file->is_archived) {
        return back()->with('error', 'This file is stored under a past school year and cannot be modified.');
    }

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

    if (! $file->isAccessibleBy(Auth::user())) {
        abort(403, 'This file is in a restricted folder you don\'t have access to.');
    }

    if ($file->is_archived) {
        return back()->with('error', 'This file is stored under a past school year and cannot be modified.');
    }

    // 1. Delete file from cloud storage - skipped for local-only files
    // (nothing there to delete), and never allowed to block the rest of
    // the delete if the cloud disk itself is unreachable.
    if ($file->storage_type !== 'local') {
        try {
            Storage::disk('cloud')->delete($file->filepath);
        } catch (\Throwable $e) {
            Log::warning("Cloud delete failed for file {$file->id}, continuing with local/database cleanup: " . $e->getMessage());
        }
    }

    // 2. Delete local copy, if any
    if ($file->local_path) {
        Storage::disk('public')->delete($file->local_path);
    }

    // 3. Delete from database
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

    if (! $file->isAccessibleBy(Auth::user())) {
        abort(403, 'This file is in a restricted folder you don\'t have access to.');
    }

    if ($file->is_archived) {
        return back()->with('error', 'This file is stored under a past school year and cannot be modified.');
    }

    $request->validate([
        'new_name' => 'required|string|max:255',
    ]);

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
    // MOVE FILE IN CLOUD STORAGE - skipped for local-only files, since
    // there's no cloud object yet to move (files:sync-to-cloud will upload
    // it under the new path once the connection recovers)
    // =========================
    if ($file->storage_type !== 'local') {
        try {
            Storage::disk('cloud')->move($oldPath, $newPath);
        } catch (\Throwable) {
            return back()->with('error', 'Failed to rename file in storage.');
        }
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