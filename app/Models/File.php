<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Folder;

class File extends Model
{
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
