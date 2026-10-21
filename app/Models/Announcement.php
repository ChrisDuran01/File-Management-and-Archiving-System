<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'attachment_path',
        'attachment_original_name',
        'created_by',
        'hide_from_search',
    ];

    protected $casts = [
        'hide_from_search' => 'boolean',
    ];

    public function images()
    {
        return $this->hasMany(AnnouncementImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The readable end of the announcement's own address: ".../12/intramurals-schedule". */
    public function slug(): string
    {
        return Str::slug($this->title) ?: 'announcement';
    }

    /**
     * The message as one line of plain text, for search results and link
     * previews. Block tags become spaces first, so "<p>One</p><p>Two</p>"
     * doesn't read "OneTwo"; inline ones like <strong> simply go.
     */
    public function summary(int $limit = 160): string
    {
        $text = preg_replace('~<(?:br|/?(?:p|div|li|ul|ol|h[1-6]|blockquote|tr|td|th))\b[^>]*>~i', ' ', $this->body);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), $limit);
    }
}
