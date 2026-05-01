<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('archives', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('record_id')->nullable();
            $table->string('folder_name');
            $table->string('zip_name');
            $table->text('file_path');
            $table->string('archive_type')->nullable();
            $table->string('archived_by')->nullable();
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamp('restored_at')->nullable();
            $table->string('status')->default('archived');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archives');
    }
};
