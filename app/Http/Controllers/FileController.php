<?php
namespace App\Http\Controllers;

use App\Jobs\ExtractFileOcrText;
use App\Jobs\SendDocumentPush;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\OfficerTerm;
use App\Models\User;
use App\Notifications\FileUploadedToYourFolder;
use App\Services\CloudFileUploader;
use App\Services\FileResponse;
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

        // The upload toast (Admin/partials/upload-script) asks for JSON so a
        // refused file shows as failed; plain form posts (the phone app) get
        // the usual redirect with a flash message.
        $reply = fn (string $type, string $message, int $status = 200, array $extra = []) => $request->expectsJson()
            ? response()->json(['message' => $message] + $extra, $status)
            : back()->with($type, $message);

        // Validate incoming files (max 1GB each, matches php.ini upload_max_filesize)
        $request->validate([
            'files.*'   => 'file|max:1048576|mimes:' . self::ALLOWED_MIMES,
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        // Ensure files exist in request
        if (! $request->hasFile('files')) {
            return $reply('error', 'No file selected', 422);
        }

        // Folders stored under a past school year are read-only
        if ($request->folder_id && Folder::find($request->folder_id)?->is_archived) {
            return $reply('error', 'This folder is stored under a past school year and is read-only.', 422);
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
            $localPath = $uploadedFile->store('uploads/' . ($request->folder_id ?? 'root'), 'local');

            $prepared[] = [
                'newFileName' => $newFileName,
                'filePath'    => $filePath,
                'localPath'   => $localPath,
                'fullPath'    => Storage::disk('local')->path($localPath),
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
        $term           = OfficerTerm::activeFor(Auth::id());
        $pendingApproval = $request->boolean('pending_approval');

        foreach ($prepared as $item) {
            $uploadedToCloud = $uploader->upload($item['filePath'], $item['fullPath']);

            if (! $uploadedToCloud) {
                $localOnlyNames[] = $item['newFileName'];
            }

            $rows[] = [
                'filename'        => $item['newFileName'],
                'filepath'        => $item['filePath'],
                'local_path'      => $item['localPath'],
                'folder_id'       => $request->folder_id ?? null,
                'size'            => $item['size'],
                'storage_type'    => $uploadedToCloud ? 'both' : 'local',
                'officer_term_id' => $term?->id,
                'school_year'     => $term?->school_year,
                'is_pending_approval' => $pendingApproval,
                'created_at'      => $now,
                'updated_at'      => $now,
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
                'activity'   => 'Uploaded ' . count($rows) . ' file(s)' . ($pendingApproval ? ' (subject for approval)' : '') . ': ' . collect($rows)->pluck('filename')->implode(', '),
                'ip_address' => $request->ip(),
            ]);

            $this->notifyFolderCreator($request->folder_id, count($rows), $rows[0]['filename']);
        }

        if ($localOnlyNames) {
            $count = count($localOnlyNames);

            return $reply('success',
                count($rows) . ' file(s) uploaded. ' . $count . ' ' . ($count === 1 ? 'couldn\'t' : 'couldn\'t')
                . ' reach cloud storage right now and ' . ($count === 1 ? 'was' : 'were')
                . ' saved locally instead - still fully usable, and will sync to the cloud automatically'
                . ' once the connection recovers: ' . implode(', ', $localOnlyNames),
                200,
                ['cloudPending' => true]
            );
        }

        return $reply('success', 'Files uploaded successfully!');
    }

    /**
     * In-app bell for the folder's creator when someone ELSE uploads into
     * their folder - a no-op for root uploads, folders without a recorded
     * creator, or uploads into your own folder.
     */
    private function notifyFolderCreator(?int $folderId, int $fileCount, string $firstFilename): void
    {
        if (! $folderId) {
            return;
        }

        $folder = Folder::find($folderId);

        if (! $folder || ! $folder->created_by || $folder->created_by === Auth::id()) {
            return;
        }

        User::find($folder->created_by)?->notify(
            new FileUploadedToYourFolder($folder->id, $folder->name, Auth::user()->name, $fileCount, $firstFilename)
        );
    }

    /**
     * Streams a file's local copy inline, for the preview page's
     * img/iframe/video/audio when FileResponse::previewUrl() had to fall
     * back to it (also used by DocumentController). Local copies are on the
     * private disk on purpose - no plain /storage/... link - so this route,
     * which checks access first, is the only way to them. Same access rule
     * as preview().
     */
    public function raw($id)
    {
        $file = File::findOrFail($id);

        if (! $file->isAccessibleBy(Auth::user())) {
            abort(403, 'This file is in a restricted folder you don\'t have access to.');
        }

        if (! $file->local_path || ! Storage::disk('local')->exists($file->local_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($file->local_path), [
            'Content-Disposition' => 'inline; filename="' . addslashes($file->filename) . '"',
        ]);
    }

    /**
     * Same local-first fallback as FileResponse::previewUrl(), but returning raw
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

        if ($file->local_path && Storage::disk('local')->exists($file->local_path)) {
            return Storage::disk('local')->get($file->local_path);
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

        $signedUrl = FileResponse::previewUrl($file);

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

    // An unofficial file can still be taken off the student page, but
    // never put on it.
    if (! $file->is_public && $file->is_pending_approval) {
        return back()->with('error', 'This file is still subject for approval. Mark it as approved before making it public.');
    }

    $file->is_public = $file->is_public == 1 ? 0 : 1;
    $file->save();

    // Super Admin / Normal User
        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Changed access for file: ' . $file->filename . ' to ' . ($file->is_public ? 'public' : 'private'),
            'ip_address' => $request->ip()
        ]);

    // Browser push to students who opted in on the public page (queued).
    // The job itself skips a file students can't see (restricted folder).
    if ($file->is_public) {
        SendDocumentPush::dispatch($file->id);
    }

    return back()->with('success', 'File access updated successfully.');
}

/**
 * Label a file as "subject for approval" (not yet official), or clear the
 * label once it has been approved. Anyone who can open the file may add
 * the label - flagging something as unofficial is the cautious direction.
 * Declaring it approved is held to the same rule as toggleAccess(): the
 * folder's creator or a SuperAdmin. So is labelling a file that is already
 * public, because that takes it off the student page.
 */
public function setApproval($id, Request $request)
{
    $file = File::findOrFail($id);
    $user = Auth::user();

    if (! $file->isAccessibleBy($user)) {
        abort(403, 'This file is in a restricted folder you don\'t have access to.');
    }

    if ($file->is_archived) {
        return back()->with('error', 'This file is stored under a past school year and cannot be modified.');
    }

    $pending = $request->boolean('pending');

    if ($file->is_pending_approval === $pending) {
        return back();
    }

    if (! $pending && ! $file->isManageableBy($user)) {
        return back()->with('error', 'Only this file\'s folder creator or a SuperAdmin can mark it as approved.');
    }

    $unpublish = $pending && $file->is_public;

    if ($unpublish && ! $file->isManageableBy($user)) {
        return back()->with('error', 'This file is public. Only its folder creator or a SuperAdmin can mark it as subject for approval.');
    }

    $file->is_pending_approval = $pending;

    if ($unpublish) {
        $file->is_public = 0;
    }

    $file->save();

    ActivityLog::create([
        'user_name'  => $user->name,
        'activity'   => ($pending ? 'Marked file as subject for approval: ' : 'Marked file as approved: ') . $file->filename
            . ($unpublish ? ' (taken off the student page)' : ''),
        'ip_address' => $request->ip(),
    ]);

    if (! $pending) {
        return back()->with('success', 'File marked as approved.');
    }

    return back()->with('success', 'File marked as subject for approval.' . ($unpublish ? ' It was taken off the student page.' : ''));
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

    // Soft delete only - the local and cloud copies are kept so this can
    // be undone from the Trash screen. Bytes are removed for real by
    // TrashPurger (permanent delete from Trash, or trash:purge-expired
    // after the retention window).
    $file->moveToTrash();

    // Log activity
     ActivityLog::create([
        'user_name' => Auth::user()->name,
        'activity' => 'Moved file to Trash: ' . $file->filename,
        'ip_address' => request()->ip()
    ]);

    return back()->with('success', 'File moved to Trash. It can be restored from there within ' . config('trash.retention_days') . ' days.');
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