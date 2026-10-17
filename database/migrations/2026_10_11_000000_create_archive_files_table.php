<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per file sealed inside an archive's ZIP, holding what search
     * matches on. FolderArchiver force-deletes the `files` rows (and, through
     * the database's ON DELETE CASCADE, their `documents` rows) once the ZIP
     * is built, which takes each file's extracted text, school year and
     * filed-document details with it - leaving an archived file findable by
     * its name only. This keeps a copy of those fields next to the archive
     * so search can still match on them without opening the ZIP.
     */
    public function up(): void
    {
        Schema::create('archive_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_id')->constrained('archives')->cascadeOnDelete();

            $table->string('filename'); // its entry name inside the ZIP
            $table->string('school_year')->nullable();
            $table->longText('ocr_text')->nullable();

            // From the file's `documents` row, when it was filed as one.
            $table->string('document_title')->nullable();
            $table->string('document_type')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_files');
    }
};
