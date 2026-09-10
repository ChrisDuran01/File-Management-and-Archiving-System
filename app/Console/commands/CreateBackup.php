<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Cache;

class CreateBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new backup';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting backup...');

        // Runs directly and synchronously, not via the queue - that
        // indirection (RunBackupJob) exists to keep the web server free to
        // answer progress-polling requests while a backup runs; a CLI
        // command already blocks its own terminal for as long as it takes,
        // so there's nothing to free up here.
        (new BackupController())->performBackup();

        // performBackup() doesn't return a result - it reports outcome via
        // the same progress cache the SuperAdmin page polls, so read that
        // back rather than always reporting success regardless of what
        // actually happened (the previous check here was always truthy).
        $progress = Cache::get('backup_progress', []);

        if (($progress['stage'] ?? null) === 'error') {
            $this->error('Backup failed: ' . ($progress['message'] ?? 'unknown error'));
            return Command::FAILURE;
        }

        $this->info($progress['message'] ?? 'Backup completed successfully!');
        return Command::SUCCESS;
    }
}