<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Services\FileResponse;
use App\Services\FileVersions;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * File versions (App\Services\FileVersions): the Versions dialog
 * (partials/file-versions), the question an upload asks when a file with
 * the same name is already in the folder, and "Make it version N" for a
 * draft saved under another name. Opening a version needs what opening the
 * file needs; changing anything needs what renaming it needs.
 */
class FileVersionController extends Controller
{
    /**
     * Which of these names are already in the folder, asked by the upload
     * form (Admin/partials/upload-script) before it sends anything. Only
     * files the officer may add a version to are listed; any other name
     * clash is saved as "(1)" like before.
     */
    public function nameCheck(Request $request)
    {
        $request->validate([
            'folder_id' => 'nullable|exists:folders,id',
            'names'     => 'required|array|max:200',
            'names.*'   => 'string|max:255',
        ]);

        $user = Auth::user();
        $folder = $request->folder_id ? Folder::find($request->folder_id) : null;

        if ($folder && ! $folder->isAccessibleBy($user)) {
            abort(403, 'This folder is restricted — you don\'t have access to it.');
        }

        $existing = File::where(fn ($q) => $folder ? $q->where('folder_id', $folder->id) : $q->whereNull('folder_id'))
            ->whereIn('filename', $request->input('names'))
            ->get()
            ->filter(fn (File $file) => FileVersions::canChange($file, $user))
            ->map(fn (File $file) => [
                'name'          => $file->filename,
                'next_version'  => $file->version_no + 1,
                'final_version' => $file->isFinal() ? $file->final_version_no : null,
                'upload_url'    => route('files.versions.store', $file->id),
            ])
            ->values();

        return response()->json(['folder' => $folder?->name, 'existing' => $existing]);
    }

    /** Everything the Versions dialog shows, newest first. */
    public function index($id)
    {
        $file = File::with(['versions.uploader:id,name', 'versionBy:id,name', 'finalBy:id,name'])->findOrFail($id);
        $user = Auth::user();
        $this->mayOpen($file);

        $canChange = FileVersions::canChange($file, $user);

        $current = [
            'no'           => $file->version_no,
            'name'         => $file->filename,
            'size'         => self::sizeLabel((int) $file->size),
            'date'         => ($file->version_at ?? $file->created_at)?->format('M d, Y · h:i A'),
            'by'           => $file->versionBy?->name,
            'note'         => $file->version_note,
            'current'      => true,
            'final'        => $file->final_version_no === $file->version_no,
            'open_url'     => route('files.preview', $file->id),
            'download_url' => route('files.download', $file->id),
            'restore_url'  => null,
        ];

        $older = $file->versions->map(fn (FileVersion $v) => [
            'no'           => $v->version_no,
            'name'         => $v->filename,
            'size'         => self::sizeLabel((int) $v->size),
            'date'         => $v->uploaded_at?->format('M d, Y · h:i A'),
            'by'           => $v->uploader?->name,
            'note'         => $v->note,
            'current'      => false,
            'final'        => $file->final_version_no === $v->version_no,
            'open_url'     => route('files.versions.show', [$file->id, $v->version_no]),
            'download_url' => route('files.versions.download', [$file->id, $v->version_no]),
            'restore_url'  => $canChange ? route('files.versions.restore', [$file->id, $v->version_no]) : null,
        ]);

        return response()->json([
            'name'          => $file->filename,
            'version_no'    => $file->version_no,
            'is_final'      => $file->isFinal(),
            'final_version' => $file->final_version_no,
            'final_note'    => $file->final_version_no
                ? 'Version ' . $file->final_version_no . ' was marked final'
                    . ($file->finalBy ? ' by ' . $file->finalBy->name : '')
                    . ($file->final_at ? ' on ' . $file->final_at->format('M j, Y') : '') . '.'
                : null,
            'versions'      => collect([$current])->concat($older)->values(),
            // Older versions that aren't kept any more: a folder archived
            // into a ZIP and restored brings back only the current version.
            'missing'       => max(0, $file->version_no - 1 - $file->versions->count()),
            'can_change'    => $canChange,
            'reason'        => $canChange ? null : 'This file is stored under a past school year. Its versions can be opened, but not changed.',
            'upload_url'    => route('files.versions.store', $file->id),
            'final_url'     => route('files.final', $file->id),
            'accept'        => '.' . str_replace(',', ',.', FileController::ALLOWED_MIMES),
        ]);
    }

    /** "Upload new version", from the Versions dialog or the same-name question at upload. */
    public function store(Request $request, $id, FileVersions $versions)
    {
        set_time_limit(0);

        $file = File::findOrFail($id);
        $user = Auth::user();
        $this->mayChange($file);

        $request->validate([
            'file' => 'required|file|max:1048576|mimes:' . FileController::ALLOWED_MIMES,
            'note' => 'nullable|string|max:250',
        ]);

        $upload = $request->file('file');
        $before = $file->filename;

        try {
            $versions->add($file, $upload->getRealPath(), $upload->getClientOriginalExtension(), $request->input('note'), $user);
        } catch (DomainException $e) {
            return $this->reply($request, 'error', $e->getMessage(), 422);
        }

        $this->log($request, 'Uploaded version ' . $file->version_no . ' of ' . $before
            . ($file->filename !== $before ? ' (now ' . $file->filename . ')' : '')
            . ($file->version_note ? ': ' . $file->version_note : ''));

        return $this->reply($request, 'success', 'Saved as version ' . $file->version_no . ' of “' . $file->filename . '”.', 200, [
            'version_no' => $file->version_no,
            'filename'   => $file->filename,
        ]);
    }

    /** Opens an older version in the browser (the current one opens on the preview page). */
    public function show($id, $no)
    {
        return $this->serve($id, (int) $no, false);
    }

    public function download($id, $no)
    {
        return $this->serve($id, (int) $no, true);
    }

    /** "Make current": the old version comes back as the newest one. */
    public function restore(Request $request, $id, $no, FileVersions $versions)
    {
        $file = File::findOrFail($id);
        $this->mayChange($file);
        $version = FileVersion::where('file_id', $file->id)->where('version_no', (int) $no)->firstOrFail();

        try {
            $versions->restore($file, $version, Auth::user());
        } catch (DomainException $e) {
            return $this->reply($request, 'error', $e->getMessage(), 422);
        }

        $this->log($request, 'Made version ' . $version->version_no . ' of ' . $file->filename . ' current again (saved as version ' . $file->version_no . ')');

        return $this->reply($request, 'success', 'Version ' . $version->version_no . ' is current again, saved as version ' . $file->version_no . '.');
    }

    public function markFinal(Request $request, $id, FileVersions $versions)
    {
        $file = File::findOrFail($id);
        $this->mayChange($file);

        $versions->markFinal($file, Auth::user());
        $this->log($request, 'Marked version ' . $file->version_no . ' of ' . $file->filename . ' as final');

        return $this->reply($request, 'success', 'Version ' . $file->version_no . ' of “' . $file->filename . '” is marked final.');
    }

    public function clearFinal(Request $request, $id, FileVersions $versions)
    {
        $file = File::findOrFail($id);
        $this->mayChange($file);

        $versions->clearFinal($file);
        $this->log($request, 'Removed the final mark from ' . $file->filename);

        return $this->reply($request, 'success', 'The final mark was removed from “' . $file->filename . '”.');
    }

    /**
     * "Make it version N of ...": a file flagged as a newer draft of an
     * older one (DuplicateDetector) becomes that file's newest version.
     * Only for that pair, only when the text is alike rather than the very
     * same bytes (an exact copy is just a copy: Trash it).
     */
    public function merge(Request $request, $id, FileVersions $versions)
    {
        $copy = File::findOrFail($id);
        $into = $copy->duplicateOf;
        $this->mayChange($copy);

        if (! $into || $copy->duplicate_dismissed_at || $copy->duplicate_similarity >= 100) {
            return back()->with('error', 'This file isn’t marked as a newer draft of another file.');
        }

        $this->mayChange($into);
        $draft = $copy->filename;

        try {
            $versions->merge($copy, $into, Auth::user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->log($request, 'Made ' . $draft . ' version ' . $into->version_no . ' of ' . $into->filename);

        return back()->with('success', '“' . $draft . '” is now version ' . $into->version_no . ' of “' . $into->filename . '”.');
    }

    private function serve($id, int $no, bool $download)
    {
        $file = File::findOrFail($id);
        $this->mayOpen($file);

        if ($no === $file->version_no) {
            return redirect()->route($download ? 'files.download' : 'files.preview', $file->id);
        }

        $version = FileVersion::where('file_id', $file->id)->where('version_no', $no)->firstOrFail();

        if ($download) {
            $this->log(request(), 'Downloaded version ' . $no . ' of ' . $file->filename);
        }

        return FileResponse::make($version, $download);
    }

    private function mayOpen(File $file): void
    {
        if (! $file->isAccessibleBy(Auth::user())) {
            abort(403, 'This file is in a restricted folder you don\'t have access to.');
        }
    }

    private function mayChange(File $file): void
    {
        $this->mayOpen($file);

        if ($file->isLockedFor(Auth::user())) {
            abort(422, 'This file is stored under a past school year and cannot be modified.');
        }
    }

    /** JSON for the dialogs' fetch calls, a redirect with a flash message for plain forms. */
    private function reply(Request $request, string $type, string $message, int $status = 200, array $extra = [])
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message] + $extra, $status)
            : back()->with($type, $message);
    }

    private function log(Request $request, string $activity): void
    {
        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => $activity,
            'ip_address' => $request->ip(),
        ]);
    }

    /** By hand: XAMPP's PHP has no intl, so Number::fileSize() throws. */
    private static function sizeLabel(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format($bytes / 1024, 2) . ' KB';
    }
}
