<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpBotLog extends Model
{
    protected $fillable = [
        'user_id',
        'question',
        'answer',
        'answered_from_guide',
        'helpful',
    ];

    protected function casts(): array
    {
        return [
            'answered_from_guide' => 'boolean',
            'helpful'             => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
