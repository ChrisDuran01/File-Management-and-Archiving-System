<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'attachment_path',
        'attachment_original_name',
        'created_by',
    ];

    public function images()
    {
        return $this->hasMany(AnnouncementImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
