<?php

namespace App\Console\Commands;

use App\Services\Api\V1\Admin\SystemMaintenance\SystemMaintenanceService;
use Illuminate\Console\Command;

class MaintenanceStatus extends Command
{
    protected $signature = 'planora:maintenance-status';

    protected $description = 'Show whether Planora is in maintenance mode';

    public function handle(): int
    {
        $status = app(SystemMaintenanceService::class)->status();
        $summary = 'Planora: '.str_replace('_', ' ', ucfirst($status['overall'])).'.';
        if ($status['active']) $this->components->warn($summary);
        else $this->components->info($summary);
        $this->line('Laravel global maintenance: '.($status['native_active'] ? 'active' : 'inactive'));
        foreach ($status['scopes'] as $scope => $enabled) {
            $this->line(str_replace('_', ' ', ucfirst($scope)).': '.($enabled ? 'active' : 'inactive'));
        }

        return self::SUCCESS;
    }
}
