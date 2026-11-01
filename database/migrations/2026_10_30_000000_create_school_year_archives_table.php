<?php

use App\Support\SchoolYear;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Archives are now made a whole school year at a time (App\Services\
 * SchoolYearArchiver). The ZIPs stay one per folder (archives table); this
 * table records the school year itself: when and how it was archived, and
 * whether it was restored - a restored school year is held out of the
 * automatic archive until someone archives it again by hand.
 *
 * Archives made before this, one folder at a time, never recorded a school
 * year. They are filed under the school year they were archived in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_year_archives', function (Blueprint $table) {
            $table->id();
            $table->string('school_year')->unique();
            $table->string('status');                  // archived | restored
            $table->string('archived_how')->nullable(); // automatic | by_hand | per_folder (before 2026-10-30)
            $table->string('archived_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('restored_by')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('archives')->whereNull('school_year')->get(['id', 'folder_record', 'archived_at', 'created_at']) as $archive) {
            $record = json_decode($archive->folder_record ?? 'null', true);
            $schoolYear = SchoolYear::isValid($record['school_year'] ?? null)
                ? $record['school_year']
                : SchoolYear::of(Carbon::parse($archive->archived_at ?? $archive->created_at));

            DB::table('archives')->where('id', $archive->id)->update(['school_year' => $schoolYear]);
        }

        $now = now();
        foreach (DB::table('archives')->whereNull('restored_at')->select('school_year')->selectRaw('MAX(archived_at) as archived_at')->groupBy('school_year')->get() as $year) {
            DB::table('school_year_archives')->insert([
                'school_year'  => $year->school_year,
                'status'       => 'archived',
                'archived_how' => 'per_folder',
                'archived_at'  => $year->archived_at,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_year_archives');
    }
};
