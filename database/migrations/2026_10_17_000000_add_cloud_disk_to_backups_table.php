<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which filesystem disk holds a backup's off-site copy. Backups used to
     * go to the 'cloud' disk (Supabase), whose free plan refuses any file
     * over 50 MB; new ones go to the 'backups' disk (Backblaze B2). Rows
     * that already have a cloud copy are marked 'cloud' so download, restore
     * and cleanup still find them where they are.
     */
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->string('cloud_disk', 20)->nullable()->after('cloud_path');
        });

        DB::table('backups')->whereNotNull('cloud_path')->update(['cloud_disk' => 'cloud']);
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn('cloud_disk');
        });
    }
};
