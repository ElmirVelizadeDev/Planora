<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MaintenanceUp extends Command
{
    protected $signature = 'planora:maintenance-up';

    protected $description = 'Disable Planora maintenance mode';

    public function handle(): int
    {
        return $this->call('up');
    }
}
