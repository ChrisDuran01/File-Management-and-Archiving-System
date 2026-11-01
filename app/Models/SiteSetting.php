<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'logo_path',
        'hero_background_path',
        'hymn',
        'hymn_image_path',
        'vision',
        'vision_image_path',
        'mission',
        'mission_image_path',
        'backup_frequency',
        'backup_enabled',
        'price_inflation_rate',
        'price_compare_years',
    ];

    protected $casts = [
        'backup_enabled'       => 'boolean',
        'price_inflation_rate' => 'float',
        'price_compare_years'  => 'integer',
    ];

    /**
     * There's only ever one row of site-wide content - creates it on first
     * access instead of requiring a seeder.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
