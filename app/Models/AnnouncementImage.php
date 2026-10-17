<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementImage extends Model
{
    protected $fillable = ['announcement_id', 'path', 'sort_order'];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
}
