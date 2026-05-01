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
        Schema::create('files', function (Blueprint $table) {
    $table->id();
    $table->string('filename');
    $table->string('filepath');            // stored file path
    $table->unsignedBigInteger('folder_id'); // relation to folder
    $table->bigInteger('size')->nullable();      // size in bytes
    $table->timestamps();

    $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
