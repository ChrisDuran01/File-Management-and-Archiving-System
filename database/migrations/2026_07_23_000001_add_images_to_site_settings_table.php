<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('hero_background_path')->nullable()->after('logo_path');
            $table->string('vision_image_path')->nullable()->after('vision');
            $table->string('mission_image_path')->nullable()->after('mission');
            $table->string('hymn_image_path')->nullable()->after('hymn');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['hero_background_path', 'vision_image_path', 'mission_image_path', 'hymn_image_path']);
        });
    }
};
