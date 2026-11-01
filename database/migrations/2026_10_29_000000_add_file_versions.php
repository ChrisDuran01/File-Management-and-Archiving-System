<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File versions (App\Services\FileVersions): a document returned for fixes
 * keeps one row in its folder, and every earlier upload is kept here.
 *
 *  - file_versions: the OLDER versions only, each with its own stored bytes
 *    (never shared with another row), who uploaded it, when, and why.
 *  - files.version_no / version_note / version_by / version_at: the same
 *    details for the current version, which stays in the files row so
 *    search, share links, the student page and the phone app need nothing
 *    new. version_at null = the file's created_at.
 *  - files.final_version_no / final_at / final_by: the version an officer
 *    marked final. It stays on that number when a newer version comes.
 *
 * dateTime, not timestamp: XAMPP's MariaDB can give a timestamp column
 * ON UPDATE CURRENT_TIMESTAMP, which would rewrite it on every save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->unsignedSmallInteger('version_no');
            $table->string('filename');
            $table->string('filepath')->nullable();
            $table->string('local_path')->nullable();
            $table->string('storage_type')->nullable();
            $table->bigInteger('size')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('uploaded_at')->nullable();

            $table->unique(['file_id', 'version_no']);
        });

        Schema::table('files', function (Blueprint $table) {
            $table->unsignedSmallInteger('version_no')->default(1);
            $table->string('version_note')->nullable();
            $table->foreignId('version_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('version_at')->nullable();
            $table->unsignedSmallInteger('final_version_no')->nullable();
            $table->dateTime('final_at')->nullable();
            $table->foreignId('final_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('final_by');
            $table->dropConstrainedForeignId('version_by');
            $table->dropColumn(['version_no', 'version_note', 'version_at', 'final_version_no', 'final_at']);
        });

        Schema::dropIfExists('file_versions');
    }
};
