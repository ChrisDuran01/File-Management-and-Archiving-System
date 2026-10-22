<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where an answer came from: 'ai' (Gemini) or 'keywords' (the officer
     * guide searched by keywords because Gemini was out of quota or busy).
     * Keeps the two apart when reading the logs for the thesis.
     */
    public function up(): void
    {
        Schema::table('help_bot_logs', function (Blueprint $table) {
            $table->string('source', 10)->default('ai')->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('help_bot_logs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
