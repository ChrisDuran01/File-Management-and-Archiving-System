<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\BackupController;

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
        
        $backupController = new BackupController();
        
        // You might need to modify your createBackup method to work in CLI
        // or create a separate method for CLI backup
        $result = $backupController->createBackup();
        
        if ($result) {
            $this->info('Backup completed successfully!');
            return Command::SUCCESS;
        } else {
            $this->error('Backup failed!');
            return Command::FAILURE;
        }
    }
}