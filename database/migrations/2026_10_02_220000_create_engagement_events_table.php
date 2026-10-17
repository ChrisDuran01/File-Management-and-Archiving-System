<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous usage events from the public student page, for Reports &
 * Analytics. Deliberately holds no personal data: no IP address, no user
 * agent string, no cookie or device ID - only what happened, to which
 * announcement/document, and a coarse device category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);                       // visit, announcement_view, document_preview, ...
            $table->unsignedBigInteger('subject_id')->nullable(); // announcement or file id
            $table->string('device', 8)->nullable();          // mobile | tablet | desktop
            $table->string('source', 8)->nullable();          // 'push' = visit came from tapping a notification
            $table->boolean('first_today')->nullable();       // visits: first visit of the day on that device
            $table->boolean('first_ever')->nullable();        // visits: first visit ever on that device
            $table->string('term', 60)->nullable();           // searches: what was typed, lower-cased
            $table->unsignedInteger('results')->nullable();   // searches: how many documents matched
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'created_at']);
            $table->index(['type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_events');
    }
};
