<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SHA-256 of the finalized (encrypted) backup ZIP, recorded at creation
     * time so a future restore can verify the file wasn't corrupted/tampered
     * with before attempting to decrypt it - mirrors what archives.checksum
     * already does for archive ZIPs. Nullable: pre-existing backup rows were
     * created before this column existed and have nothing to check against.
     */
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('includes_database');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn('checksum');
        });
    }
};
