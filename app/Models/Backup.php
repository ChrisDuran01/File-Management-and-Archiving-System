<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $fillable = [
    'name',
    'file_path',
    'cloud_path', // ✅ ADD THIS
    'size',
    'status'
];
}