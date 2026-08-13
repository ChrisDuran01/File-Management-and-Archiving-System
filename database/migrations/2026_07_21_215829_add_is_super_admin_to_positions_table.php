<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('position_name');
        });

        // Adviser and President are the two SuperAdmin-tier positions -
        // whoever actively holds either gets full system access.
        DB::table('positions')
            ->whereIn('position_name', ['Adviser', 'President'])
            ->update(['is_super_admin' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
