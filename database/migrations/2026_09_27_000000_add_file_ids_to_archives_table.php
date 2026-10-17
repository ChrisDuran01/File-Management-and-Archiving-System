<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maps each archived filename to the original `files.id` it had before
     * FolderArchiver force-deleted it. Restoring a folder recreates each
     * file with this same id (like `archives.record_id` already does for
     * the folder itself), so any `documents.file_id` pointing at it - left
     * untouched, just soft-deleted, when the folder was archived - resolves
     * again instead of staying orphaned.
     */
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->json('file_ids')->nullable()->after('file_checksums');
        });
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropColumn('file_ids');
        });
    }
};
