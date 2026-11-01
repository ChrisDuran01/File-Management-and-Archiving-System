<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One school year's place in the archive: when and how it was archived,
 * and whether it was restored since (App\Services\SchoolYearArchiver).
 * The ZIPs themselves are the archives rows, one per folder.
 */
class SchoolYearArchive extends Model
{
    protected $fillable = [
        'school_year',
        'status',
        'archived_how',
        'archived_by',
        'archived_at',
        'restored_by',
        'restored_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function isRestored(): bool
    {
        return $this->status === 'restored';
    }
}
