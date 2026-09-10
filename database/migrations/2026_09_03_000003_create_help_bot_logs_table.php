<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every question asked of the in-app help assistant, with the answer it
     * gave. Two uses: (1) a backlog of what new officers actually struggle
     * with, so the officer guide can be improved; (2) a usage record for the
     * thesis. `helpful` is an optional thumbs up/down from the asker.
     */
    public function up(): void
    {
        Schema::create('help_bot_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->boolean('answered_from_guide')->default(true);
            $table->boolean('helpful')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_bot_logs');
    }
};
