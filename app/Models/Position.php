<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Position extends Model
{
     protected $fillable = ['position_name', 'is_super_admin'];

     protected $casts = [
        'is_super_admin' => 'boolean',
     ];

     public function officerTerms()
{
    return $this->hasMany(OfficerTerm::class);
}
}


