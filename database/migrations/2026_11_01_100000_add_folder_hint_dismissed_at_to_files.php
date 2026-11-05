<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Looks like a Resolution · Move" (App\Services\FolderHint): set when an
 * officer answers "Keep it here", so the file is not suggested again, in
 * its folder or in the Reports "wrong folder" panel. Cleared when the file
 * is moved. dateTime, not timestamp, for the same MariaDB reason as
 * duplicate_dismissed_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dateTime('folder_hint_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('folder_hint_dismissed_at');
        });
    }
};
