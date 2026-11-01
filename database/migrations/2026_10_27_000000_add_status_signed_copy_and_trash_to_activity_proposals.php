<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where each proposal is: its status (draft, for signing, approved, not
     * approved, done, cancelled), when it was approved - which locks its
     * content to match the signed paper - and the scan of the signed copy,
     * a normal file in the "Activity Proposals" folder. deleted_at sends a
     * deleted proposal to the Trash instead of removing it.
     *
     * Existing proposals start as drafts. dateTime, not timestamp: XAMPP's
     * MariaDB can quietly give a timestamp column ON UPDATE CURRENT_TIMESTAMP.
     */
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('signatories')->index();
            $table->dateTime('approved_at')->nullable()->after('status');
            $table->foreignId('signed_file_id')->nullable()->after('approved_at')->constrained('files')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('signed_file_id');
            $table->dropColumn(['approved_at', 'status']);
        });
    }
};
