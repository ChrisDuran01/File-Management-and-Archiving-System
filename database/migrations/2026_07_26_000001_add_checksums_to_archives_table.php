<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `checksum` is the SHA-256 of the ZIP archive as a whole (fixity check
     * for the archive file itself). `file_checksums` maps each original
     * filename to its own SHA-256, captured before it went into the ZIP, so
     * individual files can be verified again after extraction on restore.
     */
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('file_path');
            $table->json('file_checksums')->nullable()->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropColumn(['checksum', 'file_checksums']);
        });
    }
};
