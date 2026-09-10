<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Folder;

class File extends Model
{
    use SoftDeletes;

    protected $fillable = [
    'filename',
    'filepath',
    'local_path',
    'storage_type',
    'size',
    'folder_id',
    'generated_from_template_id',
    'school_year',
    'is_archived',
    'last_accessed_at',
    'ocr_text',
    'ocr_status',
    'ocr_processed_at',
];

    protected $casts = [
        'last_accessed_at' => 'datetime',
        'ocr_processed_at' => 'datetime',
    ];

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'generated_from_template_id');
    }

    public function document()
    {
        return $this->hasOne(Document::class);
    }

    /**
     * Soft-delete this file together with its document record (if it was
     * filed as a structured document) so the pair moves to Trash - and
     * comes back from Trash - as one unit. Physical local/cloud copies are
     * untouched; only TrashPurger removes bytes.
     */
    public function moveToTrash(): void
    {
        Document::where('file_id', $this->id)->update(['deleted_at' => now()]);
        $this->delete();
    }

    /**
     * Restore from Trash, bringing the linked document record back too.
     * If the parent folder is itself in Trash, the folder row alone is
     * restored as well - a file can't live inside a deleted folder.
     */
    public function restoreFromTrash(): void
    {
        if ($this->folder_id) {
            Folder::withTrashed()->find($this->folder_id)?->restore();
        }

        $this->restore();

        Document::withTrashed()->where('file_id', $this->id)->update(['deleted_at' => null]);
    }

    /**
     * A file inherits its access rules from its folder - restricting a
     * folder restricts everything inside it. Root files (no folder) are
     * always accessible.
     */
    public function isAccessibleBy(User $user): bool
    {
        return ! $this->folder || $this->folder->isAccessibleBy($user);
    }

    /**
     * Whether $user may modify this file (rename/delete/toggle its access) -
     * mirrors isAccessibleBy() but delegates to the folder's stricter
     * isManageableBy() check. Root files (no folder) follow the same
     * "always accessible" rule as isAccessibleBy() since there's no folder
     * owner to restrict them to.
     */
    public function isManageableBy(User $user): bool
    {
        return ! $this->folder || $this->folder->isManageableBy($user);
    }

    /**
     * Mark this file (and its parent folder, if any) as just used. Used to
     * determine staleness for the automated retention/archiving job.
     */
    public function touchAccessed(): void
    {
        $this->forceFill(['last_accessed_at' => now()])->save();

        if ($this->folder_id) {
            $this->folder?->touchAccessed();
        }
    }
}
