<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ActivityProposal;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Services\TrashPurger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The recycle bin. Deleting a file/folder/document/activity proposal anywhere
 * in the app only soft-deletes it; this screen lists what's in the bin and offers Restore
 * (any officer with access) and Delete permanently / Empty trash
 * (SuperAdmin only). trash:purge-expired empties old items automatically.
 */
class TrashController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Folders shown in Trash are only ones deleted directly; files whose
        // folder is trashed are represented by that folder row (restoring it
        // brings them back together), so they're not listed twice.
        $folders = Folder::onlyTrashed()
            ->latest('deleted_at')
            ->get()
            ->filter(fn (Folder $folder) => $folder->isAccessibleBy($user))
            ->values();

        $trashedFolderIds = $folders->pluck('id')->all();

        $files = File::onlyTrashed()
            ->with(['folder' => fn ($q) => $q->withTrashed()])
            ->latest('deleted_at')
            ->get()
            ->filter(function (File $file) use ($user, $trashedFolderIds) {
                if ($file->folder_id && in_array($file->folder_id, $trashedFolderIds, true)) {
                    return false; // represented by its trashed folder row
                }

                return ! $file->folder || $file->folder->isAccessibleBy($user);
            })
            ->values();

        $documents = Document::onlyTrashed()
            ->with(['folder' => fn ($q) => $q->withTrashed(), 'file' => fn ($q) => $q->withTrashed()])
            ->latest('deleted_at')
            ->get()
            ->filter(fn (Document $doc) => ! $doc->folder || $doc->folder->isAccessibleBy($user))
            ->values();

        // Any officer can open a proposal, so every deleted one is listed.
        $proposals = ActivityProposal::onlyTrashed()->with('creator')->latest('deleted_at')->get();

        $isSuperAdmin  = Folder::isSuperAdmin($user);
        $retentionDays = config('trash.retention_days');
        $layout        = $isSuperAdmin ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.trash', compact('folders', 'files', 'documents', 'proposals', 'isSuperAdmin', 'retentionDays', 'layout'));
    }

    public function restore(Request $request, string $type, int $id)
    {
        $item = $this->findTrashed($type, $id);

        if (! $item) {
            return back()->with('error', 'That item is no longer in the Trash.');
        }

        if (! $item->isAccessibleBy(Auth::user())) {
            return back()->with('error', 'You don\'t have access to restore this item.');
        }

        $item->restoreFromTrash();

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Restored from Trash: ' . $this->label($item),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Restored successfully.');
    }

    public function destroy(Request $request, string $type, int $id, TrashPurger $purger)
    {
        if (! Folder::isSuperAdmin(Auth::user())) {
            return back()->with('error', 'Only a SuperAdmin can delete items permanently.');
        }

        $item = $this->findTrashed($type, $id);

        if (! $item) {
            return back()->with('error', 'That item is no longer in the Trash.');
        }

        $label = $this->label($item);

        match ($type) {
            'file'     => $purger->purgeFile($item),
            'folder'   => $purger->purgeFolder($item),
            'document' => $purger->purgeDocument($item),
            'proposal' => $purger->purgeProposal($item),
        };

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Permanently deleted from Trash: ' . $label,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Deleted permanently.');
    }

    public function empty(Request $request, TrashPurger $purger)
    {
        if (! Folder::isSuperAdmin(Auth::user())) {
            return back()->with('error', 'Only a SuperAdmin can empty the Trash.');
        }

        $count = 0;

        Folder::onlyTrashed()->get()->each(function (Folder $folder) use ($purger, &$count) {
            $purger->purgeFolder($folder);
            $count++;
        });

        File::onlyTrashed()->get()->each(function (File $file) use ($purger, &$count) {
            $purger->purgeFile($file);
            $count++;
        });

        Document::onlyTrashed()->get()->each(function (Document $document) use ($purger, &$count) {
            $purger->purgeDocument($document);
            $count++;
        });

        ActivityProposal::onlyTrashed()->get()->each(function (ActivityProposal $proposal) use ($purger, &$count) {
            $purger->purgeProposal($proposal);
            $count++;
        });

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Emptied the Trash ({$count} item(s) permanently deleted)",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', "Trash emptied - {$count} item(s) permanently deleted.");
    }

    private function findTrashed(string $type, int $id): File|Folder|Document|ActivityProposal|null
    {
        // Folders are eager-loaded withTrashed on files/documents so their
        // isAccessibleBy() checks still see a restricted parent folder even
        // when that folder is in the Trash too.
        return match ($type) {
            'file'     => File::onlyTrashed()->with(['folder' => fn ($q) => $q->withTrashed()])->find($id),
            'folder'   => Folder::onlyTrashed()->find($id),
            'document' => Document::onlyTrashed()->with(['folder' => fn ($q) => $q->withTrashed()])->find($id),
            'proposal' => ActivityProposal::onlyTrashed()->find($id),
            default    => null,
        };
    }

    private function label(File|Folder|Document|ActivityProposal $item): string
    {
        return match (true) {
            $item instanceof File             => 'file "' . $item->filename . '"',
            $item instanceof Folder           => 'folder "' . $item->name . '"',
            $item instanceof ActivityProposal => 'activity proposal "' . $item->title . '"',
            default                           => 'document "' . $item->title . '"',
        };
    }
}
