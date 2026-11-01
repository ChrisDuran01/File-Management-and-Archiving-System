<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What an officer typed into a document template, kept with the PDF it
     * made, so the document can be opened again to fix it or copied as the
     * start of the next one.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->json('template_fields')->nullable()->after('generated_from_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('template_fields');
        });
    }
};
