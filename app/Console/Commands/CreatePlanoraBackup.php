<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupManager;
use Illuminate\Console\Command;
use Throwable;

class CreatePlanoraBackup extends Command
{
    protected $signature = 'planora:backup {type=full : database, files, or full}';
    protected $description = 'Create and verify an encrypted Planora backup.';

    public function handle(BackupManager $backups): int
    {
        try {
            $record = $backups->create((string) $this->argument('type'), scheduled: true);
            $this->info('Backup completed and verified: '.$record['id']);
            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Backup failed. Check the server log for diagnostics.');
            return self::FAILURE;
        }
    }
}
