<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MaintenanceStatus extends Command
{
    protected $signature = 'planora:maintenance-status';

    protected $description = 'Show whether Planora is in maintenance mode';

    public function handle(): int
    {
        if (app()->isDownForMaintenance()) {
            $this->components->warn('Planora is in maintenance mode.');

            return self::SUCCESS;
        }

        $this->components->info('Planora is available.');

        return self::SUCCESS;
    }
}
