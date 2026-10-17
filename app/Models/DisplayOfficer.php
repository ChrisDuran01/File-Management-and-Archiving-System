<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An officer listed on the public student dashboard who has no login
 * account - name/position/photo are entered directly in Site Content.
 */
class DisplayOfficer extends Model
{
    protected $fillable = ['name', 'position_name', 'photo'];
}
