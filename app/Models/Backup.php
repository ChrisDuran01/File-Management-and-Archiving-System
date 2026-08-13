<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $fillable = [
    'name',
    'file_path',
    'cloud_path',
    'local_path',
    'includes_database',
    'size',
    'status'
];

    protected $casts = [
        'includes_database' => 'boolean',
    ];
}