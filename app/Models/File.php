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
    'officer_term_id',
    'last_accessed_at',
    'ocr_text',
    'ocr_status',
    'ocr_processed_at',
    'is_pending_approval',
    'content_hash',
];

    protected $casts = [
        'last_accessed_at' => 'datetime',
        'ocr_processed_at' => 'datetime',
        'is_pending_approval' => 'boolean',
        'text_terms' => 'array',
        'duplicate_dismissed_at' => 'datetime',
    ];

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * The older file this one looks like a copy of (DuplicateDetector). Null
     * once that file is in Trash, so a copy whose original was deleted stops
     * being flagged.
     */
    public function duplicateOf()
    {
        return $this->belongsTo(File::class, 'duplicate_of_id');
    }

    /** Files flagged as a copy that no officer has answered "keep both" for. */
    public function scopeFlaggedAsCopy($query)
    {
        return $query->whereNotNull('duplicate_of_id')
            ->whereNull('duplicate_dismissed_at')
            ->whereHas('duplicateOf');
    }

    /**
     * Files the anonymous student page may show: marked public AND (no
     * parent folder, or a parent folder that isn't restricted). Being public
     * on the file alone isn't enough - a restricted folder still hides
     * everything inside it from anonymous visitors. A file still subject
     * for approval is never shown either: it isn't official yet.
     */
    public function scopePubliclyVisible($query)
    {
        return $query->where('is_public', 1)
            ->where('is_pending_approval', false)
            ->where(function ($q) {
                $q->whereNull('folder_id')
                    ->orWhereHas('folder', fn ($folder) => $folder->where('is_restricted', false));
            });
    }

    /**
     * The officer term active when this file was created, if any - used
     * to scope "end term" archiving to just that school year's files.
     */
    public function officerTerm()
    {
        return $this->belongsTo(OfficerTerm::class);
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
     * Whether $user may make a link that opens this file for someone without
     * an account (FileShareLink). Anyone who can open the file - except in a
     * restricted folder, where only the folder's creator or a SuperAdmin
     * decides who outside the system sees it.
     */
    public function isShareableBy(User $user): bool
    {
        if (! $this->isAccessibleBy($user)) {
            return false;
        }

        return ! $this->folder?->is_restricted || $this->isManageableBy($user);
    }

    public function shareLinks()
    {
        return $this->hasMany(FileShareLink::class);
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
