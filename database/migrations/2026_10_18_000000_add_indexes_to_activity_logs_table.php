<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * activity_logs is the one table that keeps growing (logs are kept for
     * retention.activity_log_days, two years) and it only had its primary
     * key. Measured on 200,000 rows (2026-10-18):
     *
     *  - created_at: Activity Logs date filter 78 -> 1 ms, its counts
     *    346 -> 4 ms, dashboard failed sign-ins 116 -> 6 ms, logs:prune
     *    100 -> 16 ms;
     *  - user_name, ip_address: the "users" / "unique IPs" counts on the
     *    Activity Logs page, with no date filter, 568 -> 111 ms (all three).
     *
     * An index on activity itself was left out: about half the index size
     * for 6 -> 2 ms on the dashboard. Search (LIKE '%...%') can't use any of
     * these, by design - it matches inside words.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('user_name');
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_name']);
            $table->dropIndex(['ip_address']);
        });
    }
};
