<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links that let someone without an account open one file (see
     * FileShareLink). The link's secret is looked up by its SHA-256 hash and
     * kept only encrypted with the app key, so a copy of the database alone
     * opens nothing. Archiving a folder or emptying Trash force-deletes the
     * file, which takes its links with it (ON DELETE CASCADE).
     */
    public function up(): void
    {
        Schema::create('file_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->char('token_hash', 64)->unique();
            $table->text('token');

            // dateTime, not timestamp: on MariaDB/MySQL without
            // explicit_defaults_for_timestamp (XAMPP's MariaDB 10.4), the first
            // NOT NULL timestamp column silently gets ON UPDATE
            // CURRENT_TIMESTAMP - counting an open would reset the expiry to
            // that moment and kill the link.
            $table->dateTime('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();

            // Counted, not logged: whoever opens a link has no account, and
            // keeping their IP addresses would be personal data for nothing.
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_opened_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_share_links');
    }
};
