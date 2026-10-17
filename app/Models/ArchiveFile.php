<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What was kept about one file sealed inside an archive's ZIP, written by
 * FolderArchiver at the moment the live rows are removed: the details search
 * matches on, plus a copy of the file's (and its document's) database row so
 * a restore can put them back as they were.
 */
class ArchiveFile extends Model
{
    protected $fillable = [
        'archive_id',
        'filename',
        'school_year',
        'ocr_text',
        'document_title',
        'document_type',
        'reference_no',
        'sender',
        'recipient',
        'notes',
        'file_record',
        'document_record',
    ];

    protected $casts = [
        'file_record'     => 'array',
        'document_record' => 'array',
    ];

    public function archive()
    {
        return $this->belongsTo(Archive::class);
    }
}
