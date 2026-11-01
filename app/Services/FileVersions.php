<?php

namespace App\Services;

use App\Jobs\ExtractFileOcrText;
use App\Models\ActivityProposal;
use App\Models\Document;
use App\Models\File;
use App\Models\FileShareLink;
use App\Models\FileVersion;
use App\Models\User;
use DomainException;
use Illuminate\Http\File as LocalFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Versions of one file, for a document returned for fixes again and again:
 * the folder keeps one row, and every earlier upload stays in its history
 * with who sent it, when, and what changed.
 *
 * The files row is always the CURRENT version, so search, OCR, the copy
 * check, share links, the student page and the phone app (whose copy key
 * is built from filepath) all keep working as they did. Older versions are
 * file_versions rows, each with bytes of its own: a new version never
 * overwrites or deletes the one before, and "make current" copies the old
 * bytes instead of pointing two rows at them, so deleting one row's bytes
 * can never take another's.
 *
 * Refusals that the officer should read are DomainExceptions with the
 * message to show.
 */
class FileVersions
{
    /** What a file's text and copy check go back to when its bytes change. */
    public const FRESH_TEXT = [
        'ocr_text'             => null,
        'ocr_status'           => 'pending',
        'ocr_processed_at'     => null,
        'text_terms'           => null,
        'duplicate_of_id'      => null,
        'duplicate_similarity' => null,
    ];

    public function __construct(
        private readonly CloudFileUploader $uploader,
        private readonly DuplicateDetector $detector,
    ) {
    }

    /** Adding, restoring and marking versions follow the rule for renaming the file. */
    public static function canChange(File $file, User $user): bool
    {
        return $file->isAccessibleBy($user) && ! $file->isLockedFor($user);
    }

    /** The file's name with its current version's type: the name stays, the extension follows the file. */
    public static function nameWith(File $file, string $extension): string
    {
        $stem = pathinfo($file->filename, PATHINFO_FILENAME);

        return $extension !== '' ? $stem . '.' . strtolower($extension) : $stem;
    }

    /** Another file in the same folder already has this name. */
    public static function nameTaken(File $file, string $name, ?int $except = null): bool
    {
        return File::where(fn ($q) => $file->folder_id ? $q->where('folder_id', $file->folder_id) : $q->whereNull('folder_id'))
            ->where('filename', $name)
            ->whereKeyNot($file->id)
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->exists();
    }

    /**
     * Saves the file at $sourcePath (on this computer) as the new current
     * version of $file; the version before it goes into the history.
     */
    public function add(File $file, string $sourcePath, string $extension, ?string $note, User $by): File
    {
        $extension = strtolower($extension);
        $filename = self::nameWith($file, $extension);
        $this->refuseTakenName($file, $filename);

        $no = $file->version_no + 1;
        $localPath = Storage::disk('local')->putFileAs(
            'uploads/' . ($file->folder_id ?? 'root'),
            new LocalFile($sourcePath),
            Str::random(40) . ($extension !== '' ? '.' . $extension : '')
        );
        $fullPath = Storage::disk('local')->path($localPath);

        // Same naming as FileController::store(), plus the version number.
        $cloudPath = time() . '_' . Str::slug(pathinfo($filename, PATHINFO_FILENAME)) . '_v' . $no . '_' . uniqid() . ($extension !== '' ? '.' . $extension : '');
        // A cloud failure keeps the version on this computer; files:sync-to-cloud finishes it later.
        $inCloud = $this->uploader->upload($cloudPath, $fullPath);

        DB::transaction(function () use ($file, $filename, $cloudPath, $localPath, $fullPath, $inCloud, $no, $note, $by) {
            $this->keepCurrent($file);

            $file->forceFill([
                'filename'     => $filename,
                'filepath'     => $cloudPath,
                'local_path'   => $localPath,
                'storage_type' => $inCloud ? 'both' : 'local',
                'size'         => filesize($fullPath),
                'content_hash' => hash_file('sha256', $fullPath) ?: null,
                'version_no'   => $no,
                'version_note' => $this->cleanNote($note),
                'version_by'   => $by->id,
                'version_at'   => now(),
            ] + self::FRESH_TEXT)->save();
        });

        $this->recheck($file, readText: true);

        return $file;
    }

    /**
     * Brings an older version back as a new version (v4 "Restored from
     * version 1"), so the history never loses a step. Its bytes are copied.
     */
    public function restore(File $file, FileVersion $version, User $by): File
    {
        $temp = null;

        try {
            if ($version->local_path && Storage::disk('local')->exists($version->local_path)) {
                $source = Storage::disk('local')->path($version->local_path);
            } else {
                $temp = tempnam(sys_get_temp_dir(), 'version_');
                $contents = $version->filepath && $version->storage_type !== 'local'
                    ? rescue(fn () => Storage::disk('cloud')->get($version->filepath), null, false)
                    : null;

                if ($contents === null) {
                    throw new DomainException('Version ' . $version->version_no . ' could not be loaded from storage right now. Try again in a while.');
                }
                file_put_contents($temp, $contents);
                $source = $temp;
            }

            return $this->add($file, $source, pathinfo($version->filename, PATHINFO_EXTENSION), 'Restored from version ' . $version->version_no, $by);
        } finally {
            if ($temp) {
                @unlink($temp);
            }
        }
    }

    /**
     * A draft saved under another name ("Reso-22-revised.docx") becomes the
     * newest version of the file it was flagged as a copy of. Its row goes;
     * its bytes and already-read text become $into's current version.
     */
    public function merge(File $copy, File $into, User $by): File
    {
        if (Document::where('file_id', $copy->id)->exists()) {
            throw new DomainException('“' . $copy->filename . '” is filed as a document (Documents page), so it can’t be merged. Keep both instead.');
        }
        if (ActivityProposal::withTrashed()->where('signed_file_id', $copy->id)->exists()) {
            throw new DomainException('“' . $copy->filename . '” is the signed copy of an activity proposal, so it can’t be merged.');
        }
        if ($copy->version_no > 1) {
            throw new DomainException('“' . $copy->filename . '” has versions of its own, so it can’t be merged.');
        }

        $filename = self::nameWith($into, pathinfo($copy->filename, PATHINFO_EXTENSION));
        $this->refuseTakenName($into, $filename, $copy->id);

        DB::transaction(function () use ($copy, $into, $filename) {
            $this->keepCurrent($into);

            $into->forceFill([
                'filename'         => $filename,
                'filepath'         => $copy->filepath,
                'local_path'       => $copy->local_path,
                'storage_type'     => $copy->storage_type,
                'size'             => $copy->size,
                'content_hash'     => $copy->content_hash,
                'ocr_text'         => $copy->ocr_text,
                'ocr_status'       => $copy->ocr_status,
                'ocr_processed_at' => $copy->ocr_processed_at,
                'text_terms'       => $copy->text_terms,
                'duplicate_of_id'      => null,
                'duplicate_similarity' => null,
                'version_no'   => $into->version_no + 1,
                'version_note' => 'Was “' . $copy->filename . '”',
                // Files don't record who uploaded them; the activity log
                // says who merged it.
                'version_by'   => null,
                'version_at'   => $copy->created_at ?? now(),
            ])->save();

            // Links made for the draft now open the document it belongs to,
            // and anything flagged as a copy of the draft points there too.
            FileShareLink::where('file_id', $copy->id)->update(['file_id' => $into->id]);
            DB::table('files')->where('duplicate_of_id', $copy->id)->update(['duplicate_of_id' => $into->id]);

            // Its bytes are $into's now: the row goes, not the stored file.
            $copy->forceDelete();
        });

        $this->recheck($into, readText: $into->ocr_status !== 'done');

        return $into;
    }

    /** Marks the current version as the one accepted. Not a change to the file, so "Modified" stays. */
    public function markFinal(File $file, User $by): void
    {
        $this->quietly($file, ['final_version_no' => $file->version_no, 'final_at' => now(), 'final_by' => $by->id]);
    }

    public function clearFinal(File $file): void
    {
        $this->quietly($file, ['final_version_no' => null, 'final_at' => null, 'final_by' => null]);
    }

    /**
     * Puts the current version into the history, as it stands. Called just
     * before the files row takes new bytes - by add(), merge(), and a
     * template document's Edit (TemplateController::generate()).
     */
    public function keepCurrent(File $file): FileVersion
    {
        return FileVersion::create([
            'file_id'      => $file->id,
            'version_no'   => $file->version_no,
            'filename'     => $file->filename,
            'filepath'     => $file->filepath,
            'local_path'   => $file->local_path,
            'storage_type' => $file->storage_type,
            'size'         => $file->size,
            'content_hash' => $file->content_hash,
            'note'         => $file->version_note,
            'uploaded_by'  => $file->version_by,
            'uploaded_at'  => $file->version_at ?? $file->created_at,
        ]);
    }

    /**
     * Removes the stored bytes of every older version of $file - for
     * TrashPurger and FolderArchiver, just before the file row itself goes
     * (its file_versions rows go with it, ON DELETE CASCADE). A cloud
     * failure never blocks it, same as TrashPurger::purgeFile().
     */
    public function deleteStoredVersions(File $file): void
    {
        foreach (FileVersion::where('file_id', $file->id)->get() as $version) {
            if ($version->storage_type !== 'local' && $version->filepath && $version->filepath !== $file->filepath) {
                try {
                    Storage::disk('cloud')->delete($version->filepath);
                } catch (Throwable $e) {
                    Log::warning("Version purge: cloud delete failed for file {$file->id} v{$version->version_no}, continuing: " . $e->getMessage());
                }
            }

            if ($version->local_path && $version->local_path !== $file->local_path) {
                Storage::disk('local')->delete($version->local_path);
            }
        }
    }

    /** A version's bytes: the local copy first, otherwise the cloud copy. Null when neither can be read. */
    public static function contents(FileVersion $version): ?string
    {
        if ($version->local_path && Storage::disk('local')->exists($version->local_path)) {
            return Storage::disk('local')->get($version->local_path);
        }

        if ($version->filepath && $version->storage_type !== 'local') {
            return rescue(fn () => Storage::disk('cloud')->get($version->filepath), null, false);
        }

        return null;
    }

    private function refuseTakenName(File $file, string $filename, ?int $except = null): void
    {
        if ($filename !== $file->filename && self::nameTaken($file, $filename, $except)) {
            throw new DomainException('“' . $filename . '” is already in this folder as a separate file. Rename one of them first.');
        }
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : Str::limit($note, 250, '…');
    }

    /** Saves without changing updated_at (the folder's "Modified" column). */
    private function quietly(File $file, array $values): void
    {
        $file->timestamps = false;
        $file->forceFill($values)->save();
        $file->timestamps = true;
    }

    /** New bytes: an exact copy is known now; text and near copies once OCR has read it. */
    private function recheck(File $file, bool $readText): void
    {
        try {
            $this->detector->check($file);
        } catch (Throwable $e) {
            Log::warning('Duplicate check failed for new version of ' . $file->filename . ': ' . $e->getMessage());
        }

        if ($readText) {
            ExtractFileOcrText::dispatch($file->id);
        }
    }
}
