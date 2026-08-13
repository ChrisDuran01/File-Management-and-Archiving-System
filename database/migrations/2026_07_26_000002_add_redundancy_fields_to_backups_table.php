<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A backup previously existed only in the cloud bucket (the same one
     * the live files sit in - no real redundancy). `local_path` records a
     * second, persisted copy on local disk so losing either the cloud
     * account or the server alone doesn't lose the backup too.
     * `includes_database` records whether the mysqldump step succeeded, so
     * an admin can tell a files-only backup apart from a full one.
     */
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->string('local_path')->nullable()->after('cloud_path');
            $table->boolean('includes_database')->default(false)->after('local_path');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn(['local_path', 'includes_database']);
        });
    }
};
