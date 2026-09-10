<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A structured record for a single hardcopy document that has been
     * digitized. Each row links to the per-document split PDF (`file_id`)
     * and the `folder` it was filed into, and carries the fields an office
     * logbook needs - type, reference number, dates, sender/recipient,
     * status - so documents can be filtered, sorted and reported on rather
     * than only full-text searched. `school_year` / `is_archived` mirror
     * `files` / `folders` so the retention/read-only rules apply the same way.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('folder_id')->constrained('folders')->cascadeOnDelete();
            $table->foreignId('scan_batch_id')->nullable()->constrained('scan_batches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('document_type')->nullable()->index();
            // Memo / Letter / Resolution / Notice / Invoice / Receipt / Contract / Report / Other
            $table->string('reference_no')->nullable()->index();
            $table->date('document_date')->nullable();
            $table->date('date_received');
            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();
            $table->string('status')->default('filed');
            // filed / for_review / archived / superseded
            $table->text('notes')->nullable();

            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('start_page')->nullable();   // page range in the original batch (provenance)
            $table->unsignedInteger('end_page')->nullable();

            $table->string('school_year')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index('date_received');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
