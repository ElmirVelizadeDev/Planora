<?php

namespace App\Console\Commands;

use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;
use Illuminate\Console\Command;

class MaintenanceUp extends Command
{
    protected $signature = 'planora:maintenance-up';

    protected $description = 'Disable Planora maintenance mode';

    public function handle(): int
    {
        app(MaintenanceScopeStore::class)->disableAll();

        if (app()->isDownForMaintenance()) {
            return $this->call('up');
        }

        $this->components->info('All Planora maintenance scopes are disabled.');

        return self::SUCCESS;
    }
}
