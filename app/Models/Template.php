<?php

namespace App\Models;

use App\Support\Documents\RequestLetterDocument;
use App\Support\Documents\ResolutionDocument;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    /**
     * Categories taken off the document maker for now. Their rows stay in
     * the database, so removing a category from this list brings it back.
     */
    public const HIDDEN_CATEGORIES = ['memorandum'];

    /**
     * Categories built from their own form and layout. Any other category
     * is a plain text template with {{placeholders}} (DocumentFormatter).
     */
    public const DOCUMENTS = [
        'resolution'     => ResolutionDocument::class,
        'request_letter' => RequestLetterDocument::class,
    ];

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

    public function scopeVisible($query)
    {
        return $query->whereNotIn('category', self::HIDDEN_CATEGORIES);
    }

    /** @return class-string<\App\Support\Documents\TemplateDocument>|null */
    public function documentClass(): ?string
    {
        return self::DOCUMENTS[$this->category] ?? null;
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
