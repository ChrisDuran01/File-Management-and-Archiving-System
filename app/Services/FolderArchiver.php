<?php

namespace App\Services;

use App\Models\Archive;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Zips up a folder's files, uploads the zip, records it in the archives
 * table, and removes the live folder/files. Shared by the manual "Archive"
 * button (ArchiveController) and the automated stale-folder retention
 * command, so the two never drift out of sync.
 */
class FolderArchiver
{
    /**
     * @return array{success: bool, message: string, archivedFilesCount?: int}
     */
    public function archive(Folder $folder, string $archivedBy, string $archiveType = 'folder_archive', ?string $remarks = null): array
    {
        $folderId   = $folder->id;
        $folderName = $folder->name;

        // Snapshot the folder's access rules now - they're gone once the
        // folder row (and its cascading folder_access rows) is deleted below.
        $isRestricted   = $folder->is_restricted;
        $createdBy      = $folder->created_by;
        $allowedUserIds = $folder->allowedUsers()->pluck('users.id')->all();

        Log::info('Archive started for folder: ' . $folderName);

        $files = File::where('folder_id', $folderId)->get();

        if ($files->isEmpty()) {
            return ['success' => false, 'message' => 'No files found in this folder to archive'];
        }

        $zipName     = $folderName . '_' . now()->format('Ymd_His') . '.zip';
        $tempZipPath = storage_path('app/temp/' . $zipName);

        if (! FileFacade::exists(storage_path('app/temp'))) {
            FileFacade::makeDirectory(storage_path('app/temp'), 0777, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath, ZipArchive::CREATE) !== true) {
            return ['success' => false, 'message' => 'Could not create ZIP file'];
        }

        $archivedFilesCount = 0;
        $fileChecksums      = [];

        foreach ($files as $file) {
            $fileAdded   = false;
            $fileContent = null;

            if ($file->local_path && Storage::disk('public')->exists($file->local_path)) {
                $fileContent = Storage::disk('public')->get($file->local_path);
                $zip->addFromString($file->filename, $fileContent);
                $fileAdded = true;
                $archivedFilesCount++;
            } elseif ($file->filepath) {
                try {
                    $fileContent = Storage::disk('cloud')->get($file->filepath);
                    $zip->addFromString($file->filename, $fileContent);
                    $fileAdded = true;
                    $archivedFilesCount++;
                } catch (\Throwable) {
                    // fall through to the "not found" warning below
                }
            }

            if (! $fileAdded) {
                Log::warning('File not found: ' . $file->filename);
                continue;
            }

            // Fixity: record what each file's contents hashed to at the
            // moment it was archived, so a restore can later prove whether
            // the extracted bytes still match.
            $fileChecksums[$file->filename] = hash('sha256', $fileContent);

            if ($file->local_path && Storage::disk('public')->exists($file->local_path)) {
                Storage::disk('public')->delete($file->local_path);
            }

            if ($file->filepath) {
                Storage::disk('cloud')->delete($file->filepath);
            }

            $file->delete();
        }

        $zip->close();

        if (! FileFacade::exists($tempZipPath) || FileFacade::size($tempZipPath) === 0) {
            return ['success' => false, 'message' => 'ZIP file was not created or is empty'];
        }

        // Fixity: hash of the ZIP as a whole, so any future copy of this
        // archive (local, cloud, or a downloaded copy) can be checked against
        // this recorded value to prove it hasn't been altered or corrupted.
        $checksum = hash_file('sha256', $tempZipPath);

        $localArchivePath = 'archives/' . $zipName;
        Storage::disk('public')->put($localArchivePath, file_get_contents($tempZipPath));

        $zipContent   = file_get_contents($tempZipPath);
        $archivePath  = "archives/$zipName";

        Storage::disk('cloud')->put($archivePath, $zipContent);

        FileFacade::delete($tempZipPath);

        $folder->delete();
        Log::info('Folder deleted from main folders: ' . $folderName);

        Archive::create([
            'record_id'        => $folderId,
            'folder_name'      => $folderName,
            'zip_name'         => $zipName,
            'file_path'        => $archivePath,
            'archive_type'     => $archiveType,
            'archived_by'      => $archivedBy,
            'archived_at'      => now(),
            'status'           => 'archived',
            'remarks'          => $remarks ?? "Archived folder '{$folderName}' with {$archivedFilesCount} files. Folder removed from main directory.",
            'is_restricted'    => $isRestricted,
            'created_by'       => $createdBy,
            'allowed_user_ids' => $allowedUserIds,
            'checksum'         => $checksum,
            'file_checksums'   => $fileChecksums,
        ]);

        Log::info('Folder archived and removed successfully: ' . $folderName);

        return [
            'success'             => true,
            'message'             => "Folder '{$folderName}' archived successfully with {$archivedFilesCount} files.",
            'archivedFilesCount'  => $archivedFilesCount,
        ];
    }
}
