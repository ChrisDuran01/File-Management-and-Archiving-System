<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficerTerm extends Model

{
    protected $table = 'officer_terms'; // IMPORTANT (add this)
    
    protected $fillable = [
        'user_id',
        'position_id',
        'school_year',
        'term_start',
        'term_end',
        'status'
    ];

    protected $casts = [
        'term_start' => 'date',
        'term_end' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}