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
        Schema::create('letterheads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('border_image_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('seal_path')->nullable();
            $table->string('footer_image_path')->nullable();
            $table->string('university_name')->nullable();
            $table->string('office_name')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letterheads');
    }
};
