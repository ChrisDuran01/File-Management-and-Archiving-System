<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Folder;

class File extends Model
{
    protected $fillable = [
    'filename',
    'filepath',
    'size',
    'folder_id',
];

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }
}
