<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'position_id',
        'profile_photo',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function position()
    {
        return $this->belongsTo(\App\Models\Position::class);
    }

    public function officerTerms()
    {
        return $this->hasMany(OfficerTerm::class);
    }
    public function activeOfficerTerm()
{
    return $this->hasOne(OfficerTerm::class)
                ->where('status', 'active');
}

    /**
     * Everyone who can act inside the admin area right now: users holding an
     * active officer term, plus legacy accounts with no officer_terms row at
     * all (those count as SuperAdmin - see Folder::isSuperAdmin()). This is
     * the audience for broadcast notifications like "scan ready for review".
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public static function activeOfficers()
    {
        return static::where(function ($q) {
            $q->whereHas('officerTerms', fn ($t) => $t->where('status', 'active'))
              ->orWhereDoesntHave('officerTerms');
        })->get();
    }

    /**
     * Users who pass Folder::isSuperAdmin() - active Adviser/President term,
     * or a legacy account with no officer_terms row. Audience for
     * admin-level alerts like "backup failed".
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public static function superAdmins()
    {
        return static::where(function ($q) {
            $q->whereHas('officerTerms', function ($t) {
                $t->where('status', 'active')
                  ->whereHas('position', fn ($p) => $p->where('is_super_admin', true));
            })->orWhereDoesntHave('officerTerms');
        })->get();
    }
}
