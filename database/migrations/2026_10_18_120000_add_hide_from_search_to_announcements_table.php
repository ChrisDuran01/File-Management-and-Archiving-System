<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each announcement has its own public page that search engines may
     * list. Officers tick "Hide from Google" on posts that name students,
     * so those pages ask search engines not to list them (they still show
     * on the student page and in shared links).
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('hide_from_search')->default(false)->after('attachment_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('hide_from_search');
        });
    }
};
