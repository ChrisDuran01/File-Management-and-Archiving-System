<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records that a user has read the privacy notice, and which wording
     * (config('privacy.version')) they acknowledged, so the app can show the
     * notice again after it materially changes.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacy_acknowledged_at')->nullable();
            $table->string('privacy_notice_version', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['privacy_acknowledged_at', 'privacy_notice_version']);
        });
    }
};
