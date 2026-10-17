<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a restore needs to put an archived folder back as it was.
     * Archiving hard-deletes the folder and file rows, and the database's
     * ON DELETE CASCADE takes each file's `documents` row with them - so a
     * restore could only bring back the files themselves, in a new folder
     * with no school year, owner or restriction, and no document records.
     * These columns keep a copy of each deleted row as it stood:
     *
     * - `archives.folder_record`         the folders row
     * - `archive_files.file_record`      the files row (without ocr_text,
     *                                    which already has its own column)
     * - `archive_files.document_record`  the documents row, if it had one
     *
     * `archives.school_year` is the folder's school year on its own, so it
     * can be shown and filtered on without unpacking the copy.
     */
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->string('school_year')->nullable()->after('folder_name');
            $table->json('folder_record')->nullable()->after('file_ids');
        });

        Schema::table('archive_files', function (Blueprint $table) {
            $table->json('file_record')->nullable()->after('notes');
            $table->json('document_record')->nullable()->after('file_record');
        });
    }

    public function down(): void
    {
        Schema::table('archive_files', function (Blueprint $table) {
            $table->dropColumn(['file_record', 'document_record']);
        });

        Schema::table('archives', function (Blueprint $table) {
            $table->dropColumn(['school_year', 'folder_record']);
        });
    }
};
