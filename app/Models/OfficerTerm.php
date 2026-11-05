<?php

namespace App\Models;

use App\Support\SchoolYear;
use Illuminate\Database\Eloquent\Model;

class OfficerTerm extends Model

{
    protected $table = 'officer_terms'; // IMPORTANT (add this)
    
    protected $fillable = [
        'user_id',
        'position_id',
        'school_year',
        'term_start',
        'term_end',
        'status',
        'site_photo',
    ];

    protected $casts = [
        'term_start' => 'date',
        'term_end' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Photo shown on the public student dashboard: the one uploaded in Site
     * Content if there is one, otherwise the officer's own profile photo.
     */
    public function getDisplayPhotoAttribute(): ?string
    {
        return $this->site_photo ?: $this->user?->profile_photo;
    }

    /**
     * The term currently active for $userId, if any - used to stamp new
     * folders/files with who (which term/school_year) created them, so
     * ending a school year's term can archive just that year's content
     * instead of sweeping every unarchived folder/file. Returns null for
     * SuperAdmin accounts with no officer_terms row - their uploads simply
     * aren't tied to a school year.
     */
    public static function activeFor(?int $userId): ?self
    {
        if (! $userId) {
            return null;
        }

        return self::where('user_id', $userId)->where('status', 'active')->first();
    }

    /**
     * The school year of the officers in office now: the oldest one with an
     * active term. While End Term waits, the next President's term (the
     * year after) is active too, so the oldest is the one being handed
     * over. The Adviser stays from year to year and is left out, unless
     * the Adviser is the only one in office.
     */
    public static function currentSchoolYear(): ?string
    {
        $active = self::where('status', 'active')->whereNotNull('school_year');

        return (clone $active)
            ->whereDoesntHave('position', fn ($p) => $p->where('position_name', Position::ADVISER))
            ->min('school_year')
            ?? $active->min('school_year');
    }

    /**
     * The next President, added in End Term before the outgoing officers
     * leave: an active President term in the school year after the current
     * one. Null until someone is added.
     */
    public static function incomingPresident(): ?self
    {
        $year = self::currentSchoolYear();

        if (! SchoolYear::isValid($year)) {
            return null;
        }

        return self::with('user')
            ->where('status', 'active')
            ->where('school_year', SchoolYear::next($year))
            ->whereHas('position', fn ($p) => $p->where('position_name', Position::PRESIDENT))
            ->orderBy('id')
            ->first();
    }

    /** Active terms of the officers in office - not the next President still waiting for End Term. */
    public function scopeInOffice($query)
    {
        $year = self::currentSchoolYear();

        return $query->where('status', 'active')
            ->when($year, fn ($q) => $q->where(fn ($q) => $q->whereNull('school_year')->orWhere('school_year', '<=', $year)));
    }
}