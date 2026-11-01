<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An older version of a file (App\Services\FileVersions). The current
 * version is the files row itself; these are the ones before it, each with
 * its own stored bytes. Removed for real only with the whole file
 * (TrashPurger) or when its folder is archived into a ZIP (FolderArchiver).
 */
class FileVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'version_no',
        'filename',
        'filepath',
        'local_path',
        'storage_type',
        'size',
        'content_hash',
        'note',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
