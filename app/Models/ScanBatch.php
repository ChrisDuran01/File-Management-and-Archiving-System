<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanBatch extends Model
{
    protected $fillable = [
        'original_filename',
        'stored_disk',
        'stored_path',
        'mime_type',
        'extension',
        'checksum',
        'size',
        'source',
        'status',
        'page_count',
        'detected_separators',
        'segments',
        'segment_count',
        'dropped_pages',
        'filed_document_count',
        'target_folder_id',
        'ocr_text',
        'error',
        'processed_at',
        'filed_at',
        'filed_by',
        'school_year',
        'is_archived',
    ];

    protected $casts = [
        'segments'      => 'array',
        'dropped_pages' => 'array',
        'processed_at'  => 'datetime',
        'filed_at'      => 'datetime',
        'is_archived'   => 'boolean',
    ];

    public function targetFolder()
    {
        return $this->belongsTo(Folder::class, 'target_folder_id');
    }

    public function filedBy()
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function isReadyForReview(): bool
    {
        return $this->status === 'ready_for_review';
    }

    public function isFiled(): bool
    {
        return $this->status === 'filed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Before filing, a batch sits in the shared review queue that every
     * officer can see (same as documents.scans.index) - there's no folder
     * yet to restrict it to. Once filed, it inherits its target folder's
     * restriction rules like every other file/document, so a batch filed
     * into a restricted folder doesn't stay readable to everyone via its
     * page thumbnails after the fact.
     */
    public function isAccessibleBy(User $user): bool
    {
        return ! $this->target_folder_id || ! $this->targetFolder || $this->targetFolder->isAccessibleBy($user);
    }
}
