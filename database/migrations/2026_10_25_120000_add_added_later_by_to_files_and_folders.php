<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When, and by whom, a folder or file was added to a past school year
     * after that year was over, e.g. an old resolution from before the
     * system existed. Null for everything made during its own school year.
     * Those added-later rows are the only past-year records the Super Admin
     * may still rename or move to Trash. dateTime, not timestamp: XAMPP's
     * MariaDB can quietly give a timestamp column ON UPDATE CURRENT_TIMESTAMP.
     */
    public function up(): void
    {
        foreach (['folders', 'files'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dateTime('added_later_at')->nullable()->after('school_year');
                $table->foreignId('added_later_by')->nullable()->after('added_later_at')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['files', 'folders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('added_later_by');
                $table->dropColumn('added_later_at');
            });
        }
    }
};
