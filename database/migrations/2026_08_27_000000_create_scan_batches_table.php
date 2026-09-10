<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A scan batch is one file dropped into the watched inbox by the
     * scanner software (Epson Scan 2 / NAPS2). It is an intermediate holding
     * record - never a `files` row - because one scanned PDF can contain
     * several separate documents. Processing OCRs every page, proposes split
     * boundaries (barcode separator sheets or blank pages), and an operator
     * then reviews/corrects the boundaries and files each segment as its own
     * `documents` + `files` record.
     */
    public function up(): void
    {
        Schema::create('scan_batches', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('stored_disk')->default('public');
            $table->string('stored_path')->nullable();      // scan_batches/{id}/original.{ext}
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();
            $table->string('checksum', 64)->nullable()->index();  // sha256 - duplicate-ingestion guard
            $table->unsignedBigInteger('size')->nullable();
            $table->string('source')->default('inbox');
            $table->string('status')->default('ingesting')->index();
            // ingesting / extracting / ready_for_review / filing / filed / failed
            $table->unsignedInteger('page_count')->nullable();
            $table->string('detected_separators')->nullable();    // barcode / blank / none
            $table->json('segments')->nullable();                 // [{start_page,end_page,guessed:{...}}]
            $table->unsignedInteger('segment_count')->nullable();
            $table->json('dropped_pages')->nullable();            // separator/blank pages excluded from every segment
            $table->unsignedInteger('filed_document_count')->default(0);
            $table->foreignId('target_folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->longText('ocr_text')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('school_year')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_batches');
    }
};
