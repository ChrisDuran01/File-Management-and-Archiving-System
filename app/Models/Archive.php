<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Archive extends Model
{
    protected $fillable = [
        'record_id',
        'folder_name',
        'zip_name',
        'file_path',
        'local_path',
        'archived_by',
        'restored_by',
        'status',
        'archived_at',
        'restored_at',
        'storage_type',
        'is_restricted',
        'created_by',
        'allowed_user_ids',
        'checksum',
        'file_checksums',
    ];

      protected $casts = [
        'archived_at' => 'datetime',
        'restored_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_restricted' => 'boolean',
        'allowed_user_ids' => 'array',
        'file_checksums' => 'array',
    ];
    
    protected $dates = [
        'archived_at',
        'restored_at',
        'created_at',
        'updated_at'
    ];
    
    public function folder()
    {
        return $this->belongsTo(Folder::class, 'record_id');
    }

    /**
     * Whether $user can view/download this archive - mirrors
     * Folder::isAccessibleBy() using the access rules snapshotted at archive
     * time (the source folder itself no longer exists to check directly).
     */
    public function isAccessibleBy(User $user): bool
    {
        if (! $this->is_restricted) {
            return true;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        if (Folder::isSuperAdmin($user)) {
            return true;
        }

        return in_array($user->id, $this->allowed_user_ids ?? [], true);
    }

    /**
     * Whether $user can restore or permanently delete this archive - its
     * original creator, or SuperAdmin only (mirrors Folder::isManageableBy).
     */
    public function isManageableBy(User $user): bool
    {
        return $this->created_by === $user->id || Folder::isSuperAdmin($user);
    }

    /**
     * Whether the given ZIP bytes still hash to what was recorded when this
     * archive was created. Returns true when there's no checksum on record
     * (older archives from before fixity checking existed) rather than
     * flagging them as tampered.
     */
    public function matchesChecksum(string $zipContents): bool
    {
        if (! $this->checksum) {
            return true;
        }

        return hash_equals($this->checksum, hash('sha256', $zipContents));
    }

    public function getArchiveUrlAttribute()
    {
        if ($this->local_path && Storage::disk('public')->exists($this->local_path)) {
            return Storage::disk('public')->url($this->local_path);
        }

        return Storage::disk('cloud')->url($this->file_path);
    }
}