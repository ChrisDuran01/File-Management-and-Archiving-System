<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The source folder's is_restricted/created_by/folder_access rows are
     * gone by the time it's archived (FolderArchiver deletes the folder), so
     * a snapshot of its access rules has to be captured onto the archive
     * record itself at archive time - otherwise a restricted folder's
     * privacy is lost the moment it's archived.
     */
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->boolean('is_restricted')->default(false)->after('record_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('is_restricted');
            $table->json('allowed_user_ids')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropColumn(['is_restricted', 'created_by', 'allowed_user_ids']);
        });
    }
};
