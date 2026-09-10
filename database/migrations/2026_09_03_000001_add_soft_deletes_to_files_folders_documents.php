<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recycle-bin support: deleting a file, folder, or document record now
     * only stamps `deleted_at` (the physical local/cloud copies are kept),
     * so an accidental delete can be undone from the Trash screen. Rows are
     * only removed for real - together with their stored bytes - by a
     * permanent delete from Trash or by the scheduled trash:purge-expired
     * command after the retention window.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
