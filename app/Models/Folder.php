<?php
namespace App\Models;

use App\Models\File;
use App\Support\SchoolYear;
use App\Support\StudentPageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use WeakMap;

class Folder extends Model
{
    use SoftDeletes;

    /**
     * Answers to "is this user a SuperAdmin?" and "which restricted folders
     * was this user given?", kept per User object so a page listing many
     * folders asks the database once instead of once per folder. Emptied
     * after every request and before every queued job
     * (AppServiceProvider), so a changed role or access list is never
     * answered from an old copy.
     */
    private static ?WeakMap $superAdminAnswers = null;
    private static ?WeakMap $grantedFolderIds = null;

    protected $fillable = ['name', 'school_year', 'is_archived', 'is_restricted', 'created_by', 'officer_term_id', 'last_accessed_at', 'added_later_at', 'added_later_by'];

    protected $casts = [
        'added_later_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'is_restricted' => 'boolean',
    ];

    public function file()
    {
        return $this->hasMany(File::class);
    }

    /** Who added this folder to a past school year after that year was over. */
    public function addedLaterBy()
    {
        return $this->belongsTo(User::class, 'added_later_by');
    }

    /** Same rule as File::isLockedFor(): only added-later folders, only the Super Admin. */
    public function isLockedFor(User $user): bool
    {
        return $this->is_archived && ! ($this->added_later_at && self::isSuperAdmin($user));
    }

    /** How many school years back old records may still be added (the SG's choice, 2026-10-25). */
    public const PAST_YEARS_OPEN = 5;

    /**
     * The school years old records may be added to, newest first: the five
     * before the current one. The window moves every school year, so a year
     * closes for good once it falls out of it.
     *
     * @return string[]
     */
    public static function pastYearsOpen(): array
    {
        $years = [];
        for ($year = SchoolYear::previous(SchoolYear::current()), $i = 0; $i < self::PAST_YEARS_OPEN; $i++, $year = SchoolYear::previous($year)) {
            $years[] = $year;
        }

        return $years;
    }

    /**
     * Whether old records may be added to this school year: one of the
     * five before the current one, or the current one when its term was
     * already ended (stored away before the school year is over).
     */
    public static function canAddToPastYear(?string $schoolYear): bool
    {
        if (! SchoolYear::isValid($schoolYear)) {
            return false;
        }

        return in_array($schoolYear, self::pastYearsOpen(), true)
            || ($schoolYear === SchoolYear::current()
                && self::where('is_archived', true)->where('school_year', $schoolYear)->exists());
    }

    /**
     * $name, or "$name (1)", "$name (2)"... until no other folder on the
     * same page has it: the current folders, or one past school year.
     */
    public static function uniqueName(string $name, ?string $pastSchoolYear = null): string
    {
        $taken = fn (string $candidate) => self::where('name', $candidate)
            ->when($pastSchoolYear,
                fn ($q) => $q->where('is_archived', true)->where('school_year', $pastSchoolYear),
                fn ($q) => $q->where('is_archived', false))
            ->exists();

        $unique = $name;
        for ($n = 1; $taken($unique); $n++) {
            $unique = $name.' ('.$n.')';
        }

        return $unique;
    }

    /**
     * Where $user may move a file from $from: this school year's other
     * folders that $user can open, by name, with how many files each holds
     * (FolderHint picks the fullest one of a kind). Past school years take
     * no moved files; their records stay where they were filed.
     */
    public static function moveTargetsFor(User $user, ?Folder $from = null)
    {
        return self::where('is_archived', false)
            ->when($from, fn ($q) => $q->whereKeyNot($from->id))
            ->withCount('file')
            ->orderBy('name')
            ->get()
            ->filter(fn (Folder $folder) => $folder->isAccessibleBy($user))
            ->values();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The officer term active when this folder was created, if any - used
     * to scope "end term" archiving to just that school year's folders.
     */
    public function officerTerm()
    {
        return $this->belongsTo(OfficerTerm::class);
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

        return in_array((int) $this->id, self::grantedFolderIds($user), true);
    }

    /** Folders $user was explicitly given access to (folder_access) - one query per request. */
    private static function grantedFolderIds(User $user): array
    {
        self::$grantedFolderIds ??= new WeakMap();

        if (! isset(self::$grantedFolderIds[$user])) {
            self::$grantedFolderIds[$user] = DB::table('folder_access')
                ->where('user_id', $user->id)
                ->pluck('folder_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return self::$grantedFolderIds[$user];
    }

    /** Called after each request and before each queued job - see the properties above. */
    public static function forgetAccessAnswers(): void
    {
        self::$superAdminAnswers = null;
        self::$grantedFolderIds = null;
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
        self::$superAdminAnswers ??= new WeakMap();

        if (! isset(self::$superAdminAnswers[$user])) {
            self::$superAdminAnswers[$user] = self::checkSuperAdmin($user);
        }

        return self::$superAdminAnswers[$user];
    }

    private static function checkSuperAdmin(User $user): bool
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
        StudentPageCache::forget(); // the bulk update above skips model events

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
        StudentPageCache::forget(); // the bulk update above skips model events
    }
}
