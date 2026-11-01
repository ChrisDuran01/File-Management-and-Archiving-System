<?php

use App\Support\SchoolYear;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The letterhead new documents start with (one at a time), and the
     * school year it belongs to - the SG's letterhead changes every year.
     */
    public function up(): void
    {
        Schema::table('letterheads', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('name');
            $table->string('school_year', 9)->nullable()->after('is_default');
        });

        // A letterhead already named after its school year ("2026-2027") gets it.
        foreach (DB::table('letterheads')->get(['id', 'name']) as $row) {
            if (SchoolYear::isValid(trim($row->name))) {
                DB::table('letterheads')->where('id', $row->id)->update(['school_year' => trim($row->name)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('letterheads', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'school_year']);
        });
    }
};
