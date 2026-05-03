<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Position extends Model
{ 
     protected $fillable = ['position_name'];

     public function officerTerms()
{
    return $this->hasMany(OfficerTerm::class);
}
}


