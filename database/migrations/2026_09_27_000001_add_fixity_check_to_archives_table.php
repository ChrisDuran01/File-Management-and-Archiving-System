<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `checksum`/`file_checksums` are only ever compared reactively, at
     * restore time - an archive that's never restored could sit silently
     * corrupted for years before anyone finds out. `fixity_checked_at` and
     * `fixity_status` let a scheduled command (archives:verify-fixity)
     * re-verify periodically and record the result, so corruption surfaces
     * on its own schedule instead of only when someone happens to need
     * the file back.
     */
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->timestamp('fixity_checked_at')->nullable()->after('file_ids');
            $table->string('fixity_status', 20)->nullable()->after('fixity_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropColumn(['fixity_checked_at', 'fixity_status']);
        });
    }
};
