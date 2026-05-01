<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('backups', function (Blueprint $table) {
        $table->string('cloud_path', 255)->nullable()->after('file_path');
    });
}

public function down()
{
    Schema::table('backups', function (Blueprint $table) {
        $table->dropColumn('cloud_path');
    });
}
};
