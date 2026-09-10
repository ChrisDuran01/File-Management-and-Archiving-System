<?php
namespace App\Models;

use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Folder extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'school_year', 'is_archived', 'is_restricted', 'created_by', 'last_accessed_at'];

    protected $casts = [
        'last_accessed_at' => 'datetime',
        'is_restricted' => 'boolean',
    ];

    public function file()
    {
        return $this->hasMany(File::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Officers explicitly granted access to this restricted folder, beyond
     * its creator and SuperAdmin (who always have access).
     */
    public function allowedUsers()
    {
        return $this->belongsToMany(User::class, 'folder_access');
    }

    /**
     * Whether $user can see into this folder at all. Unrestricted folders
     * are open to everyone; a restricted folder is visible only to its
     * creator, SuperAdmin, or someone explicitly granted access.
     */
    public function isAccessibleBy(User $user): bool
    {
        if (! $this->is_restricted) {
            return true;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        if (self::isSuperAdmin($user)) {
            return true;
        }

        return $this->allowedUsers()->where('users.id', $user->id)->exists();
    }

    /**
     * Whether $user is allowed to change this folder's restriction/access
     * list - its creator, or SuperAdmin (never any other officer).
     */
    public function isManageableBy(User $user): bool
    {
        return $this->created_by === $user->id || self::isSuperAdmin($user);
    }

    /**
     * SuperAdmin means: currently holds an active Adviser or President term
     * (the two positions flagged is_super_admin on the positions table), OR
     * has no officer_terms row at all - a handful of pre-existing accounts
     * predate the Adviser/President distinction and are kept working as a
     * fallback. Same check used for the post-login dashboard redirect in
     * AuthController::login().
     */
    public static function isSuperAdmin(User $user): bool
    {
        $hasLeadershipTerm = DB::table('officer_terms')
            ->join('positions', 'officer_terms.position_id', '=', 'positions.id')
            ->where('officer_terms.user_id', $user->id)
            ->where('officer_terms.status', 'active')
            ->where('positions.is_super_admin', true)
            ->exists();

        $hasNoOfficerRecord = ! DB::table('officer_terms')->where('user_id', $user->id)->exists();

        return $hasLeadershipTerm || $hasNoOfficerRecord;
    }

    /**
     * Mark this folder as just used (opened, edited, or had a file inside it
     * previewed/downloaded/modified). Used to determine staleness for the
     * automated retention/archiving job.
     */
    public function touchAccessed(): void
    {
        $this->forceFill(['last_accessed_at' => now()])->save();
    }

    /**
     * Soft-delete this folder together with everything inside it (files and
     * their document records), so the whole thing moves to Trash as one
     * unit. Before soft deletes, this cleanup was left to the files table's
     * ON DELETE CASCADE - which no longer fires on a soft delete.
     */
    public function moveToTrash(): void
    {
        $fileIds = $this->file()->pluck('id');

        Document::whereIn('file_id', $fileIds)->update(['deleted_at' => now()]);
        File::whereIn('id', $fileIds)->update(['deleted_at' => now()]);

        $this->delete();
    }

    /**
     * Restore this folder and every trashed file (plus document records)
     * inside it. Deliberately simple: files that were trashed individually
     * before the folder was deleted come back too - erring on the side of
     * restoring more, since anything unwanted can just be deleted again.
     */
    public function restoreFromTrash(): void
    {
        $this->restore();

        $fileIds = File::withTrashed()->where('folder_id', $this->id)->pluck('id');

        File::withTrashed()->whereIn('id', $fileIds)->update(['deleted_at' => null]);
        Document::withTrashed()->whereIn('file_id', $fileIds)->update(['deleted_at' => null]);
    }
}
