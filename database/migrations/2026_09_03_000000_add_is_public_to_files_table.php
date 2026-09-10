<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `files.is_public` is read by the anonymous student dashboard and
     * written by FileController::toggleAccess(), but the column was only
     * ever added by hand on the original machine - no migration existed.
     * This records it so a fresh `php artisan migrate` produces a working
     * schema; guarded so it is a no-op where the column already exists.
     */
    public function up(): void
    {
        if (Schema::hasColumn('files', 'is_public')) {
            return;
        }

        Schema::table('files', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('generated_from_template_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('files', 'is_public')) {
            return;
        }

        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
