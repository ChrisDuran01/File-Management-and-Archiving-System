<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate detection (App\Services\DuplicateDetector):
 *  - content_hash: SHA-256 of the file's bytes, so an exact copy is caught
 *    the moment it is uploaded, before its text has been read.
 *  - text_terms: word => count for the file's extracted text, so the
 *    TF-IDF comparison doesn't re-read every file's full text each time.
 *  - duplicate_of_id / duplicate_similarity: the older file this one looks
 *    like a copy of, and how alike they are (0-100).
 *  - duplicate_dismissed_at: an officer said "not a copy, keep both".
 *    dateTime, not timestamp: XAMPP's MariaDB can give a timestamp column
 *    ON UPDATE CURRENT_TIMESTAMP, which would rewrite it on every save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->char('content_hash', 64)->nullable()->index();
            $table->longText('text_terms')->nullable();
            $table->unsignedBigInteger('duplicate_of_id')->nullable()->index();
            $table->unsignedTinyInteger('duplicate_similarity')->nullable();
            $table->dateTime('duplicate_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['content_hash']);
            $table->dropIndex(['duplicate_of_id']);
            $table->dropColumn(['content_hash', 'text_terms', 'duplicate_of_id', 'duplicate_similarity', 'duplicate_dismissed_at']);
        });
    }
};
