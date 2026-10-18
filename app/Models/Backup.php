<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Backup extends Model
{
    protected $fillable = [
    'name',
    'file_path',
    'cloud_path',
    'cloud_disk',
    'local_path',
    'includes_database',
    'checksum',
    'size',
    'status'
];

    protected $casts = [
        'includes_database' => 'boolean',
    ];

    /**
     * The disk holding this backup's off-site copy, or null when it has
     * none. Rows from before cloud_disk existed were all on 'cloud'.
     */
    public function offsiteDisk(): ?Filesystem
    {
        if (! $this->cloud_path) {
            return null;
        }

        return Storage::disk($this->cloud_disk ?: 'cloud');
    }
}
