<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Archive;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\DuplicateDetector;
use App\Services\FolderArchiver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ArchiveController extends Controller
{
    /** @var array<string, string[]> table => its column names, looked up once per request by reviveRecord() */
    private array $tableColumns = [];

    public function index()
    {
        $archives = Archive::latest()->get()->filter(fn (Archive $archive) => $archive->isAccessibleBy(Auth::user()))->values();
        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';
        return view('Admin.archives', compact('archives', 'layout'));
    }

    /**
     * Manually run the stale-folder retention check (also runs on its own
     * daily schedule) — lets a SuperAdmin trigger it on demand instead of
     * waiting for the next scheduled run.
     */
    public function runAutoArchive(Request $request)
    {
        $years = $request->filled('years') ? (int) $request->years : null;

        $exitCode = Artisan::call('folders:archive-stale', array_filter([
            '--years' => $years,
        ], fn ($v) => $v !== null));

        $output = trim(Artisan::output());

        return back()->with($exitCode === 0 ? 'success' : 'error', $output ?: 'Retention check completed.');
    }

    public function archiveFolder($folderId, FolderArchiver $archiver)
    {
        try {
            $folder = Folder::findOrFail($folderId);

            if (! $folder->isManageableBy(Auth::user())) {
                return back()->with('error', 'Only this folder\'s creator or a SuperAdmin can archive it.');
            }

            $folderName = $folder->name;

            $result = $archiver->archive($folder, Auth::user()?->name ?? 'System');

            if (! $result['success']) {
                return back()->with('error', $result['message']);
            }

            ActivityLog::create([
                'user_name'  => Auth::user()?->name ?? 'System',
                'activity'   => 'Archived folder: ' . $folderName,
                'ip_address' => request()->ip()
            ]);

            return redirect()->route('folders.index')->with('success', $result['message']);

        } catch (\Exception $e) {
            Log::error('Archive failed: ' . $e->getMessage());
            return back()->with('error', 'Archive failed: ' . $e->getMessage());
        }
    }

       /////////////////////////////////////////////////////
                          //RESTORE//
       /////////////////////////////////////////////////////
    public function restore($id)
    {
        try {
            $archive = Archive::findOrFail($id);

            if (! $archive->isManageableBy(Auth::user())) {
                return back()->with('error', 'You do not have permission to restore this archive.');
            }

            if ($archive->restored_at) {
                return back()->with('error', 'This archive has already been restored.');
            }

            // Check if folder already exists
            $existingFolder = Folder::find($archive->record_id);
            if ($existingFolder) {
                return back()->with('error', 'Folder already exists. Please delete the existing folder first or restore to a different location.');
            }

            // Put the folder back as it was when it was archived - school
            // year, owner, restriction, stored-away flag - from the copy of
            // its row FolderArchiver kept. Archives made before that copy
            // existed (pre 2026-10-11) only recorded the name and the access
            // rules, so that is all those can get back.
            $folder = new Folder();
            $folder->forceFill(array_merge(
                $this->reviveRecord('folders', $archive->folder_record ?? [
                    'id'            => $archive->record_id,
                    'name'          => $archive->folder_name,
                    'is_restricted' => $archive->is_restricted,
                    'created_by'    => $archive->created_by,
                ], ['created_by' => 'users', 'officer_term_id' => 'officer_terms']),
                // Restoring counts as using it. Otherwise a folder that was
                // auto-archived for sitting unopened would be swept straight
                // back into the archive by the next retention run.
                ['last_accessed_at' => now()]
            ))->save();

            $folder->allowedUsers()->sync(User::whereIn('id', $archive->allowed_user_ids ?? [])->pluck('id'));

            Log::info('Folder restored: ' . $folder->name);
            
            // Create restoration directory path
            $localRestorePath = 'uploads/' . $folder->id . '/';
            
            // Check if ZIP exists locally first
            $zipContent = null;
            
            // Check local archive storage
            $localArchivePath = $archive->localZipPath();
            if (file_exists($localArchivePath)) {
                $zipContent = file_get_contents($localArchivePath);
                Log::info('Restoring from local archive');
            } 
            // If not local, try the cloud disk
            else if ($archive->file_path) {
                try {
                    $zipContent = Storage::disk('cloud')->get($archive->file_path);
                    Log::info('Restoring from cloud archive');
                } catch (\Throwable) {
                    // Delete the folder we just created
                    $folder->forceDelete(); // rollback of the empty folder just created - nothing to keep in Trash
                    return back()->with('error', 'Archive file not found in any storage');
                }
            } else {
                $folder->forceDelete(); // rollback of the empty folder just created - nothing to keep in Trash
                return back()->with('error', 'Archive file location not found');
            }
            
            // Fixity: does this ZIP still match what was recorded when it was archived?
            $zipChecksumOk = $archive->matchesChecksum($zipContent);
            if (! $zipChecksumOk) {
                Log::warning('Archive checksum mismatch on restore: ' . $archive->folder_name . ' (archive #' . $archive->id . ')');
            }

            // Save ZIP temporarily
            $tempZipPath = storage_path('app/temp/restore_' . $archive->zip_name);
            if (!FileFacade::exists(storage_path('app/temp'))) {
                FileFacade::makeDirectory(storage_path('app/temp'), 0777, true);
            }
            file_put_contents($tempZipPath, $zipContent);

            // Extract ZIP
            $zip = new ZipArchive();
            $tempExtractPath = storage_path('app/temp/extract_' . time());
            
            if ($zip->open($tempZipPath) === TRUE) {
                FileFacade::makeDirectory($tempExtractPath, 0777, true);
                $zip->extractTo($tempExtractPath);
                $zip->close();
            } else {
                $folder->forceDelete(); // rollback of the empty folder just created - nothing to keep in Trash
                return back()->with('error', 'Failed to open ZIP file');
            }
            
            // Get extracted files
            $extractedFiles = FileFacade::files($tempExtractPath);
            $restoredCount = 0;
            $corruptedFiles = [];

            // Restore each file
            foreach ($extractedFiles as $extractedFile) {
                $filename = basename($extractedFile);
                $fileContent = file_get_contents($extractedFile);
                $fileSize = strlen($fileContent);

                // Fixity: does this extracted file still match the hash
                // recorded for it when it went into the ZIP?
                $expectedHash = $archive->file_checksums[$filename] ?? null;
                if ($expectedHash && ! hash_equals($expectedHash, hash('sha256', $fileContent))) {
                    $corruptedFiles[] = $filename;
                    Log::warning("Restored file failed integrity check: {$filename} (archive #{$archive->id})");
                }

                // Generate unique filename if needed
                $newFileName = $filename;
                $count = 0;
                $name = pathinfo($filename, PATHINFO_FILENAME);
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                
                while (
                    File::where('filename', $newFileName)
                    ->where('folder_id', $folder->id)
                    ->exists()
                ) {
                    $count++;
                    $newFileName = $name . " ($count)." . $extension;
                }
                
                // Generate unique file path
                $uniquePath = $folder->id . '_' . time() . '_' . Str::slug($name) . '_' . $count . '.' . $extension;
                
                // Save to local storage - under a random name, like every
                // other local copy (CacheFileLocally etc.).
                $localPath = $localRestorePath . Str::random(40) . ($extension ? '.' . $extension : '');
                Storage::disk('local')->put($localPath, $fileContent);
                
                // Upload to the cloud disk
                try {
                    Storage::disk('cloud')->put($uniquePath, $fileContent);
                    $uploadedToCloud = true;
                } catch (\Throwable) {
                    $uploadedToCloud = false;
                }

                // Start from the file's row as it was when archived (school
                // year, public / approval flags, upload date, OCR status...)
                // and point it at the bytes just written. Its extracted text
                // comes back too, so it stays findable by its contents -
                // nothing re-runs OCR here. An archive from before those
                // copies were kept only recorded each file's original id.
                $kept = $archive->files->firstWhere('filename', $filename);

                $file = new File();
                $file->forceFill(array_merge(
                    $this->reviveRecord(
                        'files',
                        $kept?->file_record ?? array_filter(['id' => $archive->file_ids[$filename] ?? null]),
                        ['officer_term_id' => 'officer_terms', 'generated_from_template_id' => 'templates']
                    ),
                    [
                        'filename'     => $newFileName,
                        'filepath'     => $uniquePath,
                        'local_path'   => $localPath,
                        'folder_id'    => $folder->id,
                        'size'         => $fileSize,
                        'storage_type' => $uploadedToCloud ? 'both' : 'local',
                        'ocr_text'     => $kept?->ocr_text,
                        'content_hash' => hash('sha256', $fileContent),
                        'duplicate_of_id'      => null,
                        'duplicate_similarity' => null,
                    ]
                ))->save();

                // Checked again like a new upload: a restored file whose twin
                // is still in the system is flagged as a possible copy.
                try {
                    $detector = app(DuplicateDetector::class);
                    $detector->indexText($file);
                    $detector->check($file);
                } catch (\Throwable $e) {
                    Log::warning('Duplicate check failed for restored file ' . $newFileName . ': ' . $e->getMessage());
                }

                // A file that was filed as a document lost that record when
                // it was archived (the database deletes it with the file
                // row), so it is rebuilt from its copy and linked to the
                // file and folder just recreated.
                if ($kept?->document_record) {
                    $document = new Document();
                    $document->forceFill(array_merge(
                        $this->reviveRecord('documents', $kept->document_record, [
                            'scan_batch_id' => 'scan_batches',
                            'created_by'    => 'users',
                        ]),
                        ['file_id' => $file->id, 'folder_id' => $folder->id]
                    ))->save();
                }

                $restoredCount++;
                Log::info('Restored file: ' . $newFileName);
            }
            
            // Clean up temp files
            FileFacade::deleteDirectory($tempExtractPath);
            FileFacade::delete($tempZipPath);
            
            // Update archive status
            $integrityNote = ! $zipChecksumOk
                ? ' WARNING: archive ZIP failed its integrity check.'
                : ($corruptedFiles ? ' WARNING: ' . count($corruptedFiles) . ' file(s) failed integrity check (' . implode(', ', $corruptedFiles) . ').' : '');

            $archive->update([
                'status' => 'restored',
                'restored_at' => now(),
                'remarks' => ($archive->remarks ? $archive->remarks . '; ' : '') . "Restored folder '{$folder->name}' with {$restoredCount} files on " . now() . $integrityNote
            ]);

            ActivityLog::create([
                'user_name'  => Auth::user()?->name ?? 'System',
                'activity'   => 'Restored folder: ' . $archive->folder_name . $integrityNote,
                'ip_address' => request()->ip()
            ]);

            if ($integrityNote) {
                return redirect()->route('folders.index')
                    ->with('error', "Folder '{$archive->folder_name}' restored with {$restoredCount} files, but failed its integrity check.{$integrityNote} The restored data may be incomplete or altered - please verify it.");
            }

            // Say where it went - a folder that had been stored away under a
            // past school year goes back there, not onto the Folders page -
            // and whether anything in it is on the student page again.
            $restoredTo = $folder->is_archived && $folder->school_year
                ? "Folder added back under Previous School Years ({$folder->school_year})."
                : 'Folder added back to main directory.';

            $publicCount = File::publiclyVisible()->where('folder_id', $folder->id)->count();
            $publicNote  = $publicCount
                ? " {$publicCount} " . Str::plural('file', $publicCount) . ' in it ' . ($publicCount === 1 ? 'is' : 'are') . ' visible on the student page again.'
                : '';

            return redirect()->route('folders.index')
                ->with('success', "Folder '{$archive->folder_name}' restored successfully with {$restoredCount} files, integrity verified. {$restoredTo}{$publicNote}");

        } catch (\Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Turns a row copied at archive time back into attributes that can be
     * inserted today: columns the table no longer has are dropped,
     * references to rows deleted in the meantime are cleared (the foreign
     * key would reject them otherwise), and the original id is kept only
     * while it is still free.
     *
     * @param  array<string, string>  $references  column => table it points into
     */
    private function reviveRecord(string $table, array $record, array $references = []): array
    {
        $this->tableColumns[$table] ??= Schema::getColumnListing($table);

        // updated_at is left for Eloquent to stamp; created_at is kept, so
        // the row still shows when it was really made.
        $attributes = Arr::except(Arr::only($record, $this->tableColumns[$table]), ['updated_at', 'deleted_at']);

        foreach ($references as $column => $referencedTable) {
            if (isset($attributes[$column]) && ! DB::table($referencedTable)->where('id', $attributes[$column])->exists()) {
                $attributes[$column] = null;
            }
        }

        // Checked on the table itself, so a trashed row counts as taken too.
        if (isset($attributes['id']) && DB::table($table)->where('id', $attributes['id'])->exists()) {
            unset($attributes['id']);
        }

        return $attributes;
    }

    /**
     * Downloading is a file response, not a redirect - a browser doesn't
     * navigate anywhere when it triggers, so a flash message set alongside
     * it would silently never be seen. When the integrity check fails, this
     * shows a real warning page requiring an explicit "Download anyway"
     * click instead, so the warning is guaranteed to actually be seen. The
     * common case (checksum passes) stays a single click, unchanged.
     */
    public function download($id, Request $request)
    {
        try {
            $archive = Archive::findOrFail($id);

            if (! $archive->isAccessibleBy(Auth::user())) {
                abort(403, 'This archive is restricted and you don\'t have access to it.');
            }

            $confirmed = $request->boolean('confirmed');

            // Try local archive storage first
            $localArchivePath = $archive->localZipPath();
            if (file_exists($localArchivePath)) {
                $verified = $archive->matchesChecksum(file_get_contents($localArchivePath));

                if (! $verified && ! $confirmed) {
                    $this->logChecksumResult($archive, false);
                    return view('Admin.archive-integrity-warning', [
                        'archive' => $archive,
                        'layout'  => Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home',
                    ]);
                }

                // Either the check passed, or they clicked through the
                // warning - either way, this is a real completed download.
                $this->logChecksumResult($archive, $verified);

                return response()->download($localArchivePath, $archive->zip_name);
            }

            // Try the cloud disk
            if ($archive->file_path && Storage::disk('cloud')->exists($archive->file_path)) {
                $zipContents = Storage::disk('cloud')->get($archive->file_path);
                $verified    = $archive->matchesChecksum($zipContents);

                if (! $verified && ! $confirmed) {
                    $this->logChecksumResult($archive, false);
                    return view('Admin.archive-integrity-warning', [
                        'archive' => $archive,
                        'layout'  => Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home',
                    ]);
                }

                $tempZipPath = storage_path('app/temp/' . $archive->zip_name);
                if (!FileFacade::exists(storage_path('app/temp'))) {
                    FileFacade::makeDirectory(storage_path('app/temp'), 0777, true);
                }
                file_put_contents($tempZipPath, $zipContents);

                $this->logChecksumResult($archive, $verified);

                return response()->download($tempZipPath, $archive->zip_name)->deleteFileAfterSend(true);
            }

            return back()->with('error', 'Archive file not found');

        } catch (\Exception $e) {
            Log::error('Download failed: ' . $e->getMessage());
            return back()->with('error', 'Download failed: ' . $e->getMessage());
        }
    }

    /**
     * Logs the outcome of a fixity check performed on download, and surfaces
     * a visible warning on the visitor's next page load when it fails -
     * response()->download() returns a file stream, not a redirect, so a
     * flash message set here won't show until the next normal request.
     */
    private function logChecksumResult(Archive $archive, bool $verified): void
    {
        ActivityLog::create([
            'user_name'  => Auth::user()?->name ?? 'System',
            'activity'   => $verified
                ? 'Downloaded archive: ' . $archive->folder_name
                : 'Downloaded archive: ' . $archive->folder_name . ' (INTEGRITY CHECK FAILED - checksum mismatch)',
            'ip_address' => request()->ip()
        ]);

        if (! $verified) {
            Log::warning('Archive checksum mismatch on download: ' . $archive->folder_name . ' (archive #' . $archive->id . ')');
            session()->flash('error', "Warning: \"{$archive->folder_name}\" failed its integrity check - the downloaded file may be corrupted or have been altered since it was archived.");
        }
    }

    /**
     * List the files preserved inside an archive - lets any signed-in
     * officer browse what was archived without downloading the whole ZIP or
     * restoring the folder. Deliberately not gated by isAccessibleBy()/
     * isManageableBy(): an officer's account is fully locked out once their
     * term ends (AuthController::login()), which would otherwise make
     * archives created by a former officer unopenable by anyone but
     * SuperAdmin. Downloading/restoring/deleting the whole archive still go
     * through those checks - this is read-only browsing only.
     */
    public function show($id)
    {
        $archive = Archive::findOrFail($id);

        $filenames = array_keys($archive->file_checksums ?? []);
        sort($filenames);

        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.archiveContents', compact('archive', 'filenames', 'layout'));
    }

    /**
     * Read-only preview of one file still sealed inside an archive's ZIP.
     * No download link is offered here on purpose - browsing an archive is
     * meant to stay "look, don't take" (the whole-ZIP download stays behind
     * isManageableBy() below).
     */
    public function previewFile($id, $filename)
    {
        $archive = Archive::findOrFail($id);

        if (! array_key_exists($filename, $archive->file_checksums ?? [])) {
            abort(404, 'File not found in this archive.');
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
        $isPdf   = $ext === 'pdf';
        $isVideo = in_array($ext, ['mp4', 'mov', 'webm', 'avi']);
        $isAudio = in_array($ext, ['mp3', 'wav', 'ogg', 'm4a']);
        $isText  = in_array($ext, ['txt', 'md', 'csv', 'log', 'json', 'xml', 'html', 'css', 'js', 'php']);

        $textContent = null;

        if ($isText) {
            $bytes = $this->readArchiveEntry($archive, $filename);
            $textContent = $bytes !== null ? substr($bytes, 0, 50000) : 'Unable to load file.';
        }

        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.archiveFilePreview', compact(
            'archive', 'filename', 'ext',
            'isImage', 'isPdf', 'isVideo', 'isAudio', 'isText',
            'textContent', 'layout'
        ));
    }

    /**
     * Streams one file's raw bytes straight out of the archive ZIP, inline
     * only (never as an attachment) - used as the src for the image/pdf/
     * video/audio viewer on the preview page above.
     */
    public function streamFile($id, $filename)
    {
        $archive = Archive::findOrFail($id);

        $contents = $this->readArchiveEntry($archive, $filename);

        if ($contents === null) {
            abort(404, 'File not found in this archive.');
        }

        $mime = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            default => 'application/octet-stream',
        };

        return response($contents)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . addslashes($filename) . '"');
    }

    /**
     * Opens the archive's ZIP (local copy first, cloud fallback - the same
     * preference order used everywhere else archives are read) and pulls
     * one entry's bytes into memory without extracting anything else.
     */
    private function readArchiveEntry(Archive $archive, string $filename): ?string
    {
        $localArchivePath = $archive->localZipPath();
        $tempPath = null;

        if (file_exists($localArchivePath)) {
            $zipPath = $localArchivePath;
        } elseif ($archive->file_path && Storage::disk('cloud')->exists($archive->file_path)) {
            if (! FileFacade::exists(storage_path('app/temp'))) {
                FileFacade::makeDirectory(storage_path('app/temp'), 0777, true);
            }

            $tempPath = storage_path('app/temp/read_' . $archive->zip_name);
            file_put_contents($tempPath, Storage::disk('cloud')->get($archive->file_path));
            $zipPath = $tempPath;
        } else {
            return null;
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            if ($tempPath) {
                @unlink($tempPath);
            }

            return null;
        }

        $contents = $zip->getFromName($filename);
        $zip->close();

        if ($tempPath) {
            @unlink($tempPath);
        }

        return $contents !== false ? $contents : null;
    }

    public function destroy($id)
    {
        try {
            $archive = Archive::findOrFail($id);

            if (! $archive->isManageableBy(Auth::user())) {
                return back()->with('error', 'You do not have permission to delete this archive.');
            }

            // Delete local archive file
            $localArchivePath = $archive->localZipPath();
            if (file_exists($localArchivePath)) {
                FileFacade::delete($localArchivePath);
                Log::info('Deleted local archive: ' . $archive->zip_name);
            }
            
            // Delete cloud archive file
            if ($archive->file_path) {
                Storage::disk('cloud')->delete($archive->file_path);
            }
            
            // Delete database record
            $folderName = $archive->folder_name;
            $archive->delete();

            ActivityLog::create([
                'user_name'  => Auth::user()?->name ?? 'System',
                'activity'   => 'Deleted archive: ' . $folderName,
                'ip_address' => request()->ip()
            ]);

            return back()->with('success', 'Archive deleted successfully');
            
        } catch (\Exception $e) {
            Log::error('Delete failed: ' . $e->getMessage());
            return back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }
}