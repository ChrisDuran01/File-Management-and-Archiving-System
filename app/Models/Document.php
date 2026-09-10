<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'file_id',
        'folder_id',
        'scan_batch_id',
        'created_by',
        'title',
        'document_type',
        'reference_no',
        'document_date',
        'date_received',
        'sender',
        'recipient',
        'status',
        'notes',
        'page_count',
        'start_page',
        'end_page',
        'school_year',
        'is_archived',
    ];

    protected $casts = [
        'document_date' => 'date',
        'date_received' => 'date',
        'is_archived'   => 'boolean',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function scanBatch()
    {
        return $this->belongsTo(ScanBatch::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A document inherits its access rules from the folder it was filed
     * into - mirrors App\Models\File so restricting a folder restricts the
     * documents inside it too.
     */
    public function isAccessibleBy(User $user): bool
    {
        return ! $this->folder || $this->folder->isAccessibleBy($user);
    }

    public function isManageableBy(User $user): bool
    {
        return ! $this->folder || $this->folder->isManageableBy($user);
    }

    /**
     * Soft-delete this document together with its PDF file, so the pair
     * moves to Trash as one unit. Physical copies stay; only TrashPurger
     * removes bytes.
     */
    public function moveToTrash(): void
    {
        File::where('id', $this->file_id)->update(['deleted_at' => now()]);
        $this->delete();
    }

    /**
     * Restore from Trash together with the PDF file (and, if needed, the
     * trashed folder row it lives in).
     */
    public function restoreFromTrash(): void
    {
        $file = File::withTrashed()->find($this->file_id);

        if ($file && $file->folder_id) {
            Folder::withTrashed()->find($file->folder_id)?->restore();
        }

        $file?->restore();
        $this->restore();
    }
}
