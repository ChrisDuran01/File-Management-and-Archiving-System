<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = [
        'name',
        'category',
        'content',
        'letterhead_id',
        'created_by',
    ];

    public function letterhead()
    {
        return $this->belongsTo(Letterhead::class);
    }

    public function files()
    {
        return $this->hasMany(File::class, 'generated_from_template_id');
    }

    /**
     * Distinct {{placeholder}} tokens found in the template body, in the
     * order they first appear — drives the auto-built fill-in form.
     */
    public function placeholders(): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $this->content, $matches);

        return array_values(array_unique($matches[1]));
    }
}
