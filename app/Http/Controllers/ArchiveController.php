<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Archive;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ArchiveController extends Controller
{
    public function index()
    {
        $archives = Archive::latest()->get();
        return view('Admin.archives', compact('archives'));
    }

    public function archiveFolder($folderId)
    {
        try {
            // Find the folder
            $folder = Folder::findOrFail($folderId);
            $folderName = $folder->name;
            
            Log::info('Archive started for folder: ' . $folderName);
            
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_SERVICE_KEY');
            $bucket = env('SUPABASE_BUCKET', 'file');
            
            if (!$supabaseUrl || !$supabaseKey) {
                return back()->with('error', 'Supabase configuration is missing');
            }

            // Get files from the folder
            $files = File::where('folder_id', $folderId)->get();

            if ($files->isEmpty()) {
                return back()->with('warning', 'No files found in this folder to archive');
            }

            // Create ZIP file
            $zipName = $folderName . '_' . now()->format('Ymd_His') . '.zip';
            $tempZipPath = storage_path('app/temp/' . $zipName);
            
            if (!FileFacade::exists(storage_path('app/temp'))) {
                FileFacade::makeDirectory(storage_path('app/temp'), 0777, true);
            }
            
            $zip = new ZipArchive();
            if ($zip->open($tempZipPath, ZipArchive::CREATE) !== true) {
                return back()->with('error', 'Could not create ZIP file');
            }

            $archivedFilesCount = 0;
            
            // Add files to ZIP
            foreach ($files as $file) {
                $fileAdded = false;
                
                // Try local storage
                if ($file->local_path && Storage::disk('public')->exists($file->local_path)) {
                    $fileContent = Storage::disk('public')->get($file->local_path);
                    $zip->addFromString($file->filename, $fileContent);
                    $fileAdded = true;
                    $archivedFilesCount++;
                    Log::info('Added local file to ZIP: ' . $file->filename);
                } 
                // Try Supabase
                else if ($file->filepath) {
                    $fileResponse = Http::withHeaders([
                        'apikey' => $supabaseKey,
                        'Authorization' => 'Bearer ' . $supabaseKey
                    ])->get("$supabaseUrl/storage/v1/object/$bucket/{$file->filepath}");
                    
                    if ($fileResponse->successful()) {
                        $zip->addFromString($file->filename, $fileResponse->body());
                        $fileAdded = true;
                        $archivedFilesCount++;
                        Log::info('Added Supabase file to ZIP: ' . $file->filename);
                    }
                }
                
                if (!$fileAdded) {
                    Log::warning('File not found: ' . $file->filename);
                }
                
                // Delete the file from database and storage
                if ($fileAdded) {
                    // Delete from local storage
                    if ($file->local_path && Storage::disk('public')->exists($file->local_path)) {
                        Storage::disk('public')->delete($file->local_path);
                    }
                    
                    // Delete from Supabase
                    if ($file->filepath) {
                        Http::withHeaders([
                            'apikey' => $supabaseKey,
                            'Authorization' => 'Bearer ' . $supabaseKey
                        ])->delete("$supabaseUrl/storage/v1/object/$bucket/{$file->filepath}");
                    }
                    
                    // Delete file record from database
                    $file->delete();
                }
            }
            
            $zip->close();
            
            if (!FileFacade::exists($tempZipPath) || FileFacade::size($tempZipPath) === 0) {
                return back()->with('error', 'ZIP file was not created or is empty');
            }
            
            // Store ZIP locally
            $localArchivePath = 'archives/' . $zipName;
            Storage::disk('public')->put($localArchivePath, file_get_contents($tempZipPath));
            
            // Upload ZIP to Supabase
            $zipContent = file_get_contents($tempZipPath);
            $archivePath = "archives/$zipName";
            
            $uploadResponse = Http::withHeaders([
                'apikey' => $supabaseKey,
                'Authorization' => 'Bearer ' . $supabaseKey
            ])->attach(
                'file',
                $zipContent,
                $zipName,
                ['Content-Type' => 'application/zip']
            )->post("$supabaseUrl/storage/v1/object/$bucket/$archivePath");
            
            // Delete temp ZIP file
            FileFacade::delete($tempZipPath);
            
            // ========== NEW: DELETE THE FOLDER FROM MAIN FOLDERS ==========
            // Store folder details before deleting
            $folderData = [
                'id' => $folder->id,
                'name' => $folder->name,
                'parent_id' => $folder->parent_id,
                'description' => $folder->description
            ];
            
            // Delete the folder from database
            $folder->delete();
            Log::info('Folder deleted from main folders: ' . $folderName);
            
            // Save archive record with folder info
            $archive = Archive::create([
                'record_id' => $folderId, // Keep the original folder ID
                'folder_name' => $folderName,
                'zip_name' => $zipName,
                'file_path' => $archivePath,
                'archive_type' => 'folder_archive',
                'archived_by' => Auth::user()?->name ?? 'System',
                'archived_at' => now(),
                'status' => 'archived',
                'remarks' => "Archived folder '{$folderName}' with {$archivedFilesCount} files. Folder removed from main directory."
            ]);
            
            Log::info('Folder archived and removed successfully: ' . $folderName);
            
            return redirect()->route('folders.index')
                ->with('success', "Folder '{$folderName}' archived successfully with {$archivedFilesCount} files. Folder removed from main directory.");

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
            
            // Check if folder already exists
            $existingFolder = Folder::find($archive->record_id);
            if ($existingFolder) {
                return back()->with('error', 'Folder already exists. Please delete the existing folder first or restore to a different location.');
            }
            
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_SERVICE_KEY');
            $bucket = env('SUPABASE_BUCKET', 'file');
            
            // Create the folder back
            $folder = Folder::create([
                'id' => $archive->record_id, // Restore with original ID if possible
                'name' => $archive->folder_name,
                'parent_id' => null, // Or get from archive remarks if stored
                'description' => "Restored from archive on " . now()
            ]);
            
            Log::info('Folder restored: ' . $folder->name);
            
            // Create restoration directory path
            $localRestorePath = 'uploads/' . $folder->id . '/';
            
            // Check if ZIP exists locally first
            $zipContent = null;
            
            // Check local archive storage
            $localArchivePath = storage_path('app/public/archives/' . $archive->zip_name);
            if (file_exists($localArchivePath)) {
                $zipContent = file_get_contents($localArchivePath);
                Log::info('Restoring from local archive');
            } 
            // If not local, try Supabase
            else if ($archive->file_path) {
                $zipResponse = Http::withHeaders([
                    'apikey' => $supabaseKey,
                    'Authorization' => 'Bearer ' . $supabaseKey
                ])->get("$supabaseUrl/storage/v1/object/$bucket/{$archive->file_path}");
                
                if ($zipResponse->successful()) {
                    $zipContent = $zipResponse->body();
                    Log::info('Restoring from Supabase archive');
                } else {
                    // Delete the folder we just created
                    $folder->delete();
                    return back()->with('error', 'Archive file not found in any storage');
                }
            } else {
                $folder->delete();
                return back()->with('error', 'Archive file location not found');
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
                $folder->delete();
                return back()->with('error', 'Failed to open ZIP file');
            }
            
            // Get extracted files
            $extractedFiles = FileFacade::files($tempExtractPath);
            $restoredCount = 0;
            
            // Restore each file
            foreach ($extractedFiles as $extractedFile) {
                $filename = basename($extractedFile);
                $fileContent = file_get_contents($extractedFile);
                $fileSize = strlen($fileContent);
                
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
                
                // Save to local storage
                $localPath = $localRestorePath . $uniquePath;
                Storage::disk('public')->put($localPath, $fileContent);
                
                // Upload to Supabase
                $uploadResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $supabaseKey,
                    'apikey' => $supabaseKey,
                ])->attach(
                    'file',
                    $fileContent,
                    $uniquePath
                )->post("$supabaseUrl/storage/v1/object/$bucket/$uniquePath");
                
                // Save to database
                $file = File::create([
                    'filename' => $newFileName,
                    'filepath' => $uniquePath,
                    'local_path' => $localPath,
                    'folder_id' => $folder->id,
                    'size' => $fileSize,
                    'storage_type' => $uploadResponse->successful() ? 'both' : 'local',
                ]);
                
                if ($file) {
                    $restoredCount++;
                    Log::info('Restored file: ' . $newFileName);
                }
            }
            
            // Clean up temp files
            FileFacade::deleteDirectory($tempExtractPath);
            FileFacade::delete($tempZipPath);
            
            // Update archive status
            $archive->update([
                'status' => 'restored',
                'restored_at' => now(),
                'remarks' => ($archive->remarks ? $archive->remarks . '; ' : '') . "Restored folder '{$folder->name}' with {$restoredCount} files on " . now()
            ]);
            
            return redirect()->route('folders.index')
                ->with('success', "Folder '{$archive->folder_name}' restored successfully with {$restoredCount} files. Folder added back to main directory.");
            
        } catch (\Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    // Keep your existing download and destroy methods
    public function download($id)
    {
        try {
            $archive = Archive::findOrFail($id);
            
            // Try local archive storage first
            $localArchivePath = storage_path('app/public/archives/' . $archive->zip_name);
            if (file_exists($localArchivePath)) {
                return response()->download($localArchivePath, $archive->zip_name);
            }
            
            // Try Supabase
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_SERVICE_KEY');
            $bucket = env('SUPABASE_BUCKET', 'file');
            
            if ($archive->file_path) {
                $zipResponse = Http::withHeaders([
                    'apikey' => $supabaseKey,
                    'Authorization' => 'Bearer ' . $supabaseKey
                ])->get("$supabaseUrl/storage/v1/object/$bucket/{$archive->file_path}");
                
                if ($zipResponse->successful()) {
                    $tempZipPath = storage_path('app/temp/' . $archive->zip_name);
                    if (!FileFacade::exists(storage_path('app/temp'))) {
                        FileFacade::makeDirectory(storage_path('app/temp'), 0777, true);
                    }
                    file_put_contents($tempZipPath, $zipResponse->body());
                    
                    return response()->download($tempZipPath, $archive->zip_name)->deleteFileAfterSend(true);
                }
            }
            
            return back()->with('error', 'Archive file not found');
            
        } catch (\Exception $e) {
            Log::error('Download failed: ' . $e->getMessage());
            return back()->with('error', 'Download failed: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $archive = Archive::findOrFail($id);
            
            // Delete local archive file
            $localArchivePath = storage_path('app/public/archives/' . $archive->zip_name);
            if (file_exists($localArchivePath)) {
                FileFacade::delete($localArchivePath);
                Log::info('Deleted local archive: ' . $archive->zip_name);
            }
            
            // Delete Supabase archive file
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_SERVICE_KEY');
            $bucket = env('SUPABASE_BUCKET', 'file');
            
            if ($archive->file_path) {
                Http::withHeaders([
                    'apikey' => $supabaseKey,
                    'Authorization' => 'Bearer ' . $supabaseKey
                ])->delete("$supabaseUrl/storage/v1/object/$bucket/{$archive->file_path}");
            }
            
            // Delete database record
            $archive->delete();
            
            return back()->with('success', 'Archive deleted successfully');
            
        } catch (\Exception $e) {
            Log::error('Delete failed: ' . $e->getMessage());
            return back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }
}