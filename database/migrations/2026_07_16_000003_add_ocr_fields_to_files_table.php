<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->longText('ocr_text')->nullable()->after('size');
            $table->string('ocr_status')->default('pending')->after('ocr_text');
            $table->timestamp('ocr_processed_at')->nullable()->after('ocr_status');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn(['ocr_text', 'ocr_status', 'ocr_processed_at']);
        });
    }
};
