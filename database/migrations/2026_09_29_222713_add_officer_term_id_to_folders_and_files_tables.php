<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->foreignId('officer_term_id')->nullable()->after('created_by')
                ->constrained('officer_terms')->nullOnDelete();
        });

        Schema::table('files', function (Blueprint $table) {
            $table->foreignId('officer_term_id')->nullable()->after('folder_id')
                ->constrained('officer_terms')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('officer_term_id');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('officer_term_id');
        });
    }
};
