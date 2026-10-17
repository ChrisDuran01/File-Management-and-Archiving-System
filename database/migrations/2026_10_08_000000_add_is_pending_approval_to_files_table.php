<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Officers often upload a file before it has been approved - a draft
     * resolution, a project proposal still waiting for signatures. This
     * flag marks such a file as "subject for approval" so it is labelled as
     * not yet official everywhere it appears, and kept off the student page
     * until it is marked approved (FileController::setApproval()).
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->boolean('is_pending_approval')->default(false)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('is_pending_approval');
        });
    }
};
