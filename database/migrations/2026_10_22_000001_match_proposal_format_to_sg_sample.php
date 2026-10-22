<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brings activity_proposals in line with a real SG proposal (FRESHIVAL
     * 2026): its heading lists Title, Theme, Date, Proponent and Budget (no
     * venue or participants), the objectives open with a few paragraphs
     * before the numbered list, the schedule's third column is
     * "Venue/Personnel" with the Master of Ceremony in a last row, and it
     * ends with five groups of signatories.
     */
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->string('theme')->nullable()->after('title');
            $table->string('proponent')->nullable()->after('ends_on');
            $table->text('objectives_intro')->nullable()->after('rationale');
            $table->string('master_of_ceremony', 255)->nullable()->after('schedule');
            $table->json('signatories')->nullable()->after('master_of_ceremony');
        });

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn(['venue', 'participants']);
        });

        // Schedule rows said who was "in charge"; the format calls the same
        // column Venue/Personnel.
        DB::table('activity_proposals')->whereNotNull('schedule')->orderBy('id')->each(function ($row) {
            $schedule = collect(json_decode($row->schedule, true) ?: [])
                ->map(fn ($r) => [
                    'time'      => $r['time'] ?? '',
                    'activity'  => $r['activity'] ?? '',
                    'personnel' => $r['personnel'] ?? $r['in_charge'] ?? '',
                ])->all();
            DB::table('activity_proposals')->where('id', $row->id)->update(['schedule' => json_encode($schedule)]);
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn(['theme', 'proponent', 'objectives_intro', 'master_of_ceremony', 'signatories']);
            $table->string('venue')->nullable();
            $table->string('participants')->nullable();
        });
    }
};
