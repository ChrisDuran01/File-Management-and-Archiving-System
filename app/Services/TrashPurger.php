<?php

namespace App\Services;

use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The one place trashed files/folders/documents are removed for real -
 * cloud object, local copy, and database row. Everything else in the app
 * only soft-deletes; this runs from the Trash screen's "Delete permanently"
 * actions and from the scheduled trash:purge-expired command.
 */
class TrashPurger
{
    /**
     * Permanently remove a (trashed) file: cloud object, local copy, row.
     * The linked document row (if any) goes with it via the database's
     * ON DELETE CASCADE, which does fire on a forceDelete.
     */
    public function purgeFile(File $file): void
    {
        // A cloud failure never blocks the purge - same policy as the old
        // hard-delete path in FileController.
        if ($file->storage_type !== 'local' && $file->filepath) {
            try {
                Storage::disk('cloud')->delete($file->filepath);
            } catch (\Throwable $e) {
                Log::warning("Trash purge: cloud delete failed for file {$file->id}, continuing: " . $e->getMessage());
            }
        }

        if ($file->local_path) {
            Storage::disk('public')->delete($file->local_path);
        }

        $file->forceDelete();
    }

    /**
     * Permanently remove a (trashed) document and its PDF file.
     */
    public function purgeDocument(Document $document): void
    {
        $file = File::withTrashed()->find($document->file_id);

        if ($file) {
            $this->purgeFile($file); // FK cascade removes the document row too
        }

        // In case the file was already gone (or never existed), make sure
        // the document row itself is removed.
        if (Document::withTrashed()->whereKey($document->id)->exists()) {
            $document->forceDelete();
        }
    }

    /**
     * Permanently remove a (trashed) folder and everything inside it. Files
     * are purged one by one first so their cloud/local bytes actually get
     * cleaned up - the folder's own FK cascade would only remove rows.
     */
    public function purgeFolder(Folder $folder): void
    {
        File::withTrashed()
            ->where('folder_id', $folder->id)
            ->get()
            ->each(fn (File $file) => $this->purgeFile($file));

        $folder->forceDelete();
    }
}
