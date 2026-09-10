<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /** Filterable list of filed document records. */
    public function index(Request $request)
    {
        $query = Document::query()->with(['folder', 'file']);

        if ($request->filled('q')) {
            $term = $request->input('q');
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('reference_no', 'like', "%{$term}%")
                  ->orWhere('sender', 'like', "%{$term}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('document_type', $request->input('type'));
        }

        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->input('folder_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('date_received', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date_received', '<=', $request->input('to'));
        }

        if (! $request->boolean('show_archived')) {
            $query->where('is_archived', false);
        }

        $documents = $query->latest('date_received')->get()
            ->filter(fn (Document $d) => $d->isAccessibleBy(Auth::user()))
            ->values();

        $folders = Folder::where('is_archived', false)->orderBy('name')->get()
            ->filter(fn (Folder $f) => $f->isAccessibleBy(Auth::user()))
            ->values();

        $types  = ScanReviewController::DOCUMENT_TYPES;
        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.documents.index', compact('documents', 'folders', 'types', 'layout'));
    }

    public function show(Document $document)
    {
        abort_unless($document->isAccessibleBy(Auth::user()), 403, 'This document is in a restricted folder you don\'t have access to.');

        $signedUrl = $document->file ? $this->resolvePreviewUrl($document->file) : null;
        $document->file?->touchAccessed();

        $documentTypes = ScanReviewController::DOCUMENT_TYPES;
        $statuses      = ScanReviewController::STATUSES;
        $layout        = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.documents.show', compact('document', 'signedUrl', 'documentTypes', 'statuses', 'layout'));
    }

    public function edit(Document $document)
    {
        abort_unless($document->isManageableBy(Auth::user()), 403);

        $documentTypes = ScanReviewController::DOCUMENT_TYPES;
        $statuses      = ScanReviewController::STATUSES;
        $layout        = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.documents.edit', compact('document', 'documentTypes', 'statuses', 'layout'));
    }

    public function update(Request $request, Document $document)
    {
        abort_unless($document->isManageableBy(Auth::user()), 403);

        if ($document->is_archived) {
            return back()->with('error', 'This document is stored under a past school year and cannot be modified.');
        }

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'document_type' => 'nullable|string|max:100',
            'reference_no'  => 'nullable|string|max:100',
            'document_date' => 'nullable|date',
            'date_received' => 'required|date',
            'sender'        => 'nullable|string|max:255',
            'recipient'     => 'nullable|string|max:255',
            'status'        => 'nullable|string|max:50',
            'notes'         => 'nullable|string',
        ]);

        $document->update($data);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Updated document: ' . $document->title,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('documents.show', $document)->with('success', 'Document updated.');
    }

    public function destroy(Request $request, Document $document)
    {
        abort_unless($document->isManageableBy(Auth::user()), 403);

        if ($document->is_archived) {
            return back()->with('error', 'This document is stored under a past school year and cannot be modified.');
        }

        $title = $document->title;

        // Soft delete only - the PDF's local/cloud copies are kept so this
        // can be undone from the Trash screen. Bytes are removed for real
        // by TrashPurger.
        $document->moveToTrash();

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Moved document to Trash: ' . $title,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('documents.index')->with('success', 'Document moved to Trash. It can be restored from there within ' . config('trash.retention_days') . ' days.');
    }

    /**
     * Signed cloud URL for the document's PDF, falling back to the local
     * public URL. Copied from FileController::resolvePreviewUrl().
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
}
