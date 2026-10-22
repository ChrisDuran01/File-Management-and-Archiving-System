<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detailed Activity Proposals, written in the system instead of in Word.
     * The five parts the SG uses: rationale, objectives, methods and
     * approaches, time and schedule, and budgetary requirements (one row
     * per budget line in activity_proposal_items).
     *
     * Objectives and the schedule are short lists that are only ever read
     * whole, so they sit in JSON columns; budget rows get their own table so
     * totals per category and search by particular stay simple queries.
     */
    public function up(): void
    {
        Schema::create('activity_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('venue')->nullable();
            $table->string('participants')->nullable();

            $table->text('rationale')->nullable();
            $table->json('objectives')->nullable();
            $table->text('methods')->nullable();
            $table->json('schedule')->nullable();

            $table->string('school_year', 9)->nullable()->index();
            // The maker's position when it was written ("SG Vice
            // President"), kept as text: terms end and positions change
            // hands, but the proposal should still say who prepared it.
            $table->string('prepared_by_position')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('officer_term_id')->nullable()->constrained('officer_terms')->nullOnDelete();
            $table->foreignId('copied_from_id')->nullable()->constrained('activity_proposals')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_proposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_proposal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('category', 100)->nullable();
            $table->string('particular');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_proposal_items');
        Schema::dropIfExists('activity_proposals');
    }
};
