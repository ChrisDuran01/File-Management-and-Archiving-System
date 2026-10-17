<?php

namespace App\Models;

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
}