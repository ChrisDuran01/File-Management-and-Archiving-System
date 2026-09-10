<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backup frequency/enabled were previously kept in the PHP session
     * (BackupController::toggle()/setFrequency()) - which belongs to one
     * browser's cookie and is invisible from the scheduler's own process
     * (`php artisan schedule:run`, invoked by cron/Task Scheduler with no
     * browser session at all). routes/console.php read them via
     * session('backup_frequency', 'daily'), which in that console context
     * silently always returned the default - so the SuperAdmin frequency
     * dropdown and enable/disable toggle never actually affected the real
     * schedule. Moving them onto the persisted site_settings row makes them
     * readable from any process.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('backup_frequency')->default('daily')->after('mission_image_path');
            $table->boolean('backup_enabled')->default(true)->after('backup_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['backup_frequency', 'backup_enabled']);
        });
    }
};
