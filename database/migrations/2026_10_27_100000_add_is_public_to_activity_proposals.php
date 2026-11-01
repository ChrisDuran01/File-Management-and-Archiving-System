<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Show on student page" switch. Off for every proposal until its
     * maker or the Super Admin turns it on; even then the student page
     * shows it only once it is approved (or done, or cancelled after).
     */
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('signed_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
