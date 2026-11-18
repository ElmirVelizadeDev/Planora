<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupManager;
use Illuminate\Console\Command;
use Throwable;

class PrunePlanoraBackups extends Command
{
    protected $signature = 'planora:backups-prune';
    protected $description = 'Apply Planora backup retention and remove stale temporary backup data.';

    public function handle(BackupManager $backups): int
    {
        try {
            $result = $backups->prune();
            $this->info("Removed {$result['removed']} expired backups; retained {$result['retained']} valid backups.");
            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Backup retention cleanup failed. Check the server log for diagnostics.');
            return self::FAILURE;
        }
    }
}
