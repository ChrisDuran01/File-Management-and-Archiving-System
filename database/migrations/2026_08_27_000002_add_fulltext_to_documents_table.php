<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A FULLTEXT index over the human-entered metadata so document search
     * scales past a `LIKE '%...%'` table scan. Guarded so the migration is a
     * no-op on a driver without FULLTEXT support (SQLite in tests) - the
     * `/documents` filter and SearchController fall back to LIKE either way.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE documents ADD FULLTEXT documents_fulltext (title, reference_no, sender, recipient, notes)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE documents DROP INDEX documents_fulltext');
        } catch (\Throwable $e) {
            // Index was never created (e.g. up() ran on a non-MySQL driver) - nothing to drop.
        }
    }
};
