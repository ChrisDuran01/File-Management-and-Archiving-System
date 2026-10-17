<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Browser push subscriptions from the public student page. Anonymous on
     * purpose: no user_id, name or email - just the opaque endpoint + keys
     * the browser hands us when a student taps "Notify me".
     */
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique(); // sha256(endpoint) - TEXT can't be uniquely indexed
            $table->string('p256dh_key');
            $table->string('auth_token');
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
