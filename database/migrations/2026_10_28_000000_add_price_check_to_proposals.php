<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The budget price check (App\Services\PriceCheck): the officer's reason
     * when a price that looks unusual is right, kept with its budget row, and
     * the Super Admin's two settings for bringing older prices up to today's
     * value. 3% a year is the middle of the BSP's 2-4% inflation target, a
     * starting point until the Super Admin enters the PSA's figure.
     */
    public function up(): void
    {
        Schema::table('activity_proposal_items', function (Blueprint $table) {
            $table->string('price_note', 300)->nullable()->after('unit_price');
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->decimal('price_inflation_rate', 5, 2)->default(3)->after('backup_enabled');
            $table->unsignedTinyInteger('price_compare_years')->default(3)->after('price_inflation_rate');
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposal_items', function (Blueprint $table) {
            $table->dropColumn('price_note');
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['price_inflation_rate', 'price_compare_years']);
        });
    }
};
