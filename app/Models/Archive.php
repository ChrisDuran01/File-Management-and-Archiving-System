<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Archive extends Model
{
    protected $fillable = [
        'record_id',
        'folder_name',
        'school_year',
        'zip_name',
        'file_path',
        'local_path',
        'archive_type',
        'remarks',
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
        'file_ids',
        'folder_record',
        'fixity_checked_at',
        'fixity_status',
    ];

      protected $casts = [
        'archived_at' => 'datetime',
        'restored_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'fixity_checked_at' => 'datetime',
        'is_restricted' => 'boolean',
        'allowed_user_ids' => 'array',
        'file_checksums' => 'array',
        'file_ids' => 'array',
        'folder_record' => 'array',
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
     * What was kept about each file inside this archive's ZIP: the details
     * search matches on, and a copy of its database rows for restore. Empty
     * for archives created before 2026-10-11, which only recorded filenames
     * (in file_checksums).
     */
    public function files()
    {
        return $this->hasMany(ArchiveFile::class);
    }

    /**
     * Where this archive's ZIP is kept on this server. Deliberately the
     * private `local` disk and not `public`: nothing on it is served by the
     * web server, so the only way to the bytes is through ArchiveController,
     * which checks who is asking first.
     */
    public function localZipPath(): string
    {
        return Storage::disk('local')->path('archives/' . $this->zip_name);
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
        // Fail closed: with no recorded checksum there is nothing to verify
        // against, so the ZIP can't be vouched for. Returning true here would
        // let anyone who clears the column make a tampered ZIP pass.
        if (! $this->checksum) {
            return false;
        }

        return hash_equals($this->checksum, hash('sha256', $zipContents));
    }
}