<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Archive;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Models\SchoolYearArchive;
use App\Models\User;
use App\Services\DuplicateDetector;
use App\Services\SchoolYearArchiver;
use App\Support\SchoolYear;
use Illuminate\Support\Arr;
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

    /**
     * The archive, a school year at a time: each archived school year with
     * what is in it (only the folders this officer may open), and - for the
     * Adviser/President - the finished school years waiting to be archived.
     */
    public function index(SchoolYearArchiver $archiver)
    {
        $user = Auth::user();
        $records = SchoolYearArchive::all()->keyBy('school_year');

        $years = Archive::whereNull('restored_at')->get()
            ->filter(fn (Archive $archive) => $archive->isAccessibleBy($user))
            ->groupBy('school_year')
            ->map(fn ($archives, $schoolYear) => [
                'school_year'      => $schoolYear,
                'folders'          => $archives->count(),
                'files'            => $archives->sum(fn (Archive $archive) => count($archive->filenames())),
                'archived_at'      => $records[$schoolYear]->archived_at ?? $archives->max('archived_at'),
                'how'              => $records[$schoolYear]->archived_how ?? null,
                'by'               => $records[$schoolYear]->archived_by ?? null,
                'fixity_failed'    => $archives->where('fixity_status', 'failed')->count(),
                'fixity_unchecked' => $archives->whereNotIn('fixity_status', ['ok', 'failed'])->count(),
            ])
            ->sortKeysDesc()
            ->values();

        $isSuperAdmin = Folder::isSuperAdmin($user);
        $waiting = $isSuperAdmin ? $archiver->waiting() : collect();
        $retentionYears = SchoolYearArchiver::retentionYears();
        $layout = $isSuperAdmin ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.archives', compact('years', 'waiting', 'retentionYears', 'isSuperAdmin', 'layout'));
    }

    /** One archived school year: its folders, one archive (ZIP) each. */
    public function schoolYear(string $schoolYear)
    {
        $user = Auth::user();
        $archives = Archive::where('school_year', $schoolYear)->whereNull('restored_at')->orderBy('folder_name')->get()
            ->filter(fn (Archive $archive) => $archive->isAccessibleBy($user))
            ->values();

        abort_if($archives->isEmpty(), 404);

        $record = SchoolYearArchive::where('school_year', $schoolYear)->first();
        $isSuperAdmin = Folder::isSuperAdmin($user);
        $layout = $isSuperAdmin ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.archiveSchoolYear', compact('schoolYear', 'archives', 'record', 'isSuperAdmin', 'layout'));
    }

    /**
     * "Archive now" (Adviser/President): a finished school year, before its
     * automatic date. The same work the nightly archives:school-years does.
     */
    public function archiveSchoolYear(string $schoolYear, SchoolYearArchiver $archiver)
    {
        if ($refusal = $archiver->refusal($schoolYear)) {
            return back()->with('error', $refusal);
        }

        $result = $archiver->archive($schoolYear, Auth::user()->name, 'by_hand');

        if ($result['folders'] === 0) {
            return back()->with('error', "Nothing from SY {$schoolYear} could be archived. " . implode(' ', $result['failed']));
        }

        $message = "SY {$schoolYear} archived: {$result['folders']} " . Str::plural('folder', $result['folders'])
            . ", {$result['files']} " . Str::plural('file', $result['files']) . '.'
            . ($result['empty'] ? " {$result['empty']} empty " . Str::plural('folder', $result['empty']) . ' had nothing to archive and stayed under the school year.' : '');

        return redirect()->route('archives.schoolYear', $schoolYear)
            ->with($result['failed'] ? 'error' : 'success', $message . ($result['failed'] ? ' Not archived: ' . implode('; ', $result['failed']) : ''));
    }

    /**
     * Brings a whole school year back (Adviser/President), under that school
     * year as before: read-only, still searchable. It then stays out of the
     * automatic archive until someone archives it again by hand.
     */
    public function restoreSchoolYear(string $schoolYear)
    {
        $archives = Archive::where('school_year', $schoolYear)->whereNull('restored_at')->get();

        if ($archives->isEmpty()) {
            return back()->with('error', "Nothing from SY {$schoolYear} is in the archive.");
        }

        $folders = 0;
        $files = 0;
        $problems = [];
        $folderIds = [];

        foreach ($archives as $archive) {
            $result = $this->restoreArchive($archive);

            if ($result['ok']) {
                $folders++;
                $files += $result['files'];
                $folderIds[] = $result['folder_id'];
                if ($result['warning']) {
                    $problems[] = $archive->folder_name . ':' . $result['warning'];
                }
            } else {
                $problems[] = $archive->folder_name . ': ' . $result['message'];
            }
        }

        if ($folders > 0) {
            SchoolYearArchive::updateOrCreate(['school_year' => $schoolYear], [
                'status'      => 'restored',
                'restored_by' => Auth::user()->name,
                'restored_at' => now(),
            ]);
        }

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Restored school year {$schoolYear}: {$folders} folder(s), {$files} file(s)"
                . ($problems ? '. WARNING: ' . implode('; ', $problems) : ''),
            'ip_address' => request()->ip(),
        ]);

        // Files that were on the student page when archived are there again.
        $public = File::publiclyVisible()->whereIn('folder_id', $folderIds)->count();

        $message = "SY {$schoolYear} restored: {$folders} " . Str::plural('folder', $folders) . ", {$files} " . Str::plural('file', $files)
            . ", back under Previous School Years ({$schoolYear}). It stays there until someone archives it again from Archives."
            . ($public ? " {$public} " . Str::plural('file', $public) . ' in it ' . ($public === 1 ? 'is' : 'are') . ' on the student page again.' : '');

        return redirect()->route('folders.index')->with(
            $problems ? 'error' : 'success',
            $message . ($problems ? ' Problems: ' . implode('; ', $problems) . ' Please check these folders.' : '')
        );
    }

    /**
     * Puts one folder's archive (ZIP) back: the folder under its school
     * year, its files, their document records, checked against the
     * checksums recorded when it was archived.
     *
     * @return array{ok: bool, message: string, files: int, warning: string, folder_id: ?int}
     */
    private function restoreArchive(Archive $archive): array
    {
        $fail = fn (string $message) => ['ok' => false, 'message' => $message, 'files' => 0, 'warning' => '', 'folder_id' => null];

        try {
            if ($archive->restored_at) {
                return $fail('already restored');
            }

            if (Folder::find($archive->record_id)) {
                return $fail('a folder with its id already exists');
            }

            // Put the folder back as it was when it was archived - owner,
            // restriction, ... - from the copy of its row FolderArchiver
            // kept, under the school year it was archived with: a restored
            // school year is read-only, like any finished one. Archives
            // made before that copy existed (pre 2026-10-11) only recorded
            // the name and the access rules, so that is all those get back.
            $folder = new Folder();
            $folder->forceFill(array_merge(
                $this->reviveRecord('folders', $archive->folder_record ?? [
                    'id'            => $archive->record_id,
                    'name'          => $archive->folder_name,
                    'is_restricted' => $archive->is_restricted,
                    'created_by'    => $archive->created_by,
                ], ['created_by' => 'users', 'officer_term_id' => 'officer_terms', 'added_later_by' => 'users']),
                ['school_year' => $archive->school_year, 'is_archived' => true, 'last_accessed_at' => now()]
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
                    return $fail('its ZIP was not found on this computer or in cloud storage');
                }
            } else {
                $folder->forceDelete(); // rollback of the empty folder just created - nothing to keep in Trash
                return $fail('its ZIP was not found on this computer and has no cloud copy');
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
            $tempExtractPath = storage_path('app/temp/extract_' . uniqid());

            if ($zip->open($tempZipPath) === TRUE) {
                FileFacade::makeDirectory($tempExtractPath, 0777, true);
                $zip->extractTo($tempExtractPath);
                $zip->close();
            } else {
                $folder->forceDelete(); // rollback of the empty folder just created - nothing to keep in Trash
                return $fail('its ZIP could not be opened');
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
                        ['officer_term_id' => 'officer_terms', 'generated_from_template_id' => 'templates', 'added_later_by' => 'users',
                         'version_by' => 'users', 'final_by' => 'users']
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
                        // Under its school year, read-only, like its folder.
                        'school_year'  => ($kept?->file_record['school_year'] ?? null) ?: $archive->school_year,
                        'is_archived'  => true,
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

            return ['ok' => true, 'message' => '', 'files' => $restoredCount, 'warning' => $integrityNote, 'folder_id' => $folder->id];

        } catch (\Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            return $fail('restore failed: ' . $e->getMessage());
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
            // The details (paths, storage errors) go to the log, not the page.
            Log::error('Download failed: ' . $e->getMessage());
            return back()->with('error', 'The archive could not be downloaded. Try again, or ask the Adviser/President to check the logs.');
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
     * List the files preserved inside an archive - lets an officer browse
     * what was archived without downloading the whole ZIP or restoring the
     * folder. Same rule as the download and the Archives list: an archive of
     * a restricted folder is for its creator, the officers it was shared
     * with and the Adviser/President (Archive::isAccessibleBy). Browsing,
     * previewing and streaming one file all check it, or the archive's ID in
     * the address would be enough to read a restricted folder.
     */
    public function show($id)
    {
        $archive = $this->accessibleArchive($id);

        $filenames = $archive->filenames();
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
        $archive = $this->accessibleArchive($id);

        if (! in_array($filename, $archive->filenames(), true)) {
            abort(404, 'File not found in this archive.');
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
        $isPdf   = $ext === 'pdf';
        $isDocx  = $ext === 'docx'; // drawn by docx-preview; old .doc files have no viewer
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
            'isImage', 'isPdf', 'isDocx', 'isVideo', 'isAudio', 'isText',
            'textContent', 'layout'
        ));
    }

    /**
     * Streams one file's raw bytes straight out of the archive ZIP, inline
     * only (never as an attachment) - used as the src for the image/video/
     * audio viewer on the preview page above, and fetched by its PDF.js
     * viewer for PDFs.
     *
     * View only: opened on its own in a tab or frame, a PDF would show in
     * the browser's own viewer, with Download and Print. Browsers say where
     * a request will be shown (Sec-Fetch-Dest), so those are refused.
     */
    public function streamFile(Request $request, $id, $filename)
    {
        if (in_array($request->header('Sec-Fetch-Dest'), ['document', 'iframe', 'frame', 'embed', 'object'], true)) {
            abort(403, 'Archived files are view only. Open this file from its archive.');
        }

        $archive = $this->accessibleArchive($id);

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

        // Shown inside the preview page only (img, video, PDF.js). The CSP
        // keeps an archived SVG or anything else from running script even if
        // a browser without Sec-Fetch-Dest opens it on its own.
        return response($contents)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . addslashes($filename) . '"')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    /** The archive, if the signed-in officer may see into it (Archive::isAccessibleBy). */
    public static function accessibleArchive($id): Archive
    {
        $archive = Archive::findOrFail($id);

        abort_unless($archive->isAccessibleBy(Auth::user()), 403, 'This archive is restricted and you don\'t have access to it.');

        return $archive;
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
            return back()->with('error', 'The archive could not be deleted. Try again, or ask the Adviser/President to check the logs.');
        }
    }
}