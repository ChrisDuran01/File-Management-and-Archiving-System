<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Archive extends Model
{
    protected $fillable = [
        'record_id',
        'folder_name',
        'zip_name',
        'file_path',
        'local_path',
        'archived_by',
        'restored_by',
        'status',
        'archived_at',
        'restored_at',
        'storage_type'
    ];

      protected $casts = [
        'archived_at' => 'datetime',
        'restored_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    protected $dates = [
        'archived_at',
        'restored_at',
        'created_at',
        'updated_at'
    ];
    
    public function folder()
    {
        return $this->belongsTo(Folder::class, 'record_id');
    }
    
    public function getArchiveUrlAttribute()
    {
        if ($this->local_path && Storage::disk('public')->exists($this->local_path)) {
            return Storage::disk('public')->url($this->local_path);
        }
        
        // Return Supabase URL if needed
        $supabaseUrl = env('SUPABASE_URL');
        $bucket = env('SUPABASE_BUCKET', 'file');
        return "$supabaseUrl/storage/v1/object/public/$bucket/{$this->file_path}";
    }
}