<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class MaintenanceDown extends Command
{
    protected $signature = 'planora:maintenance-down
        {--message= : Public message displayed during maintenance}
        {--estimated-at= : Optional estimated availability time}
        {--retry=60 : Retry-After value in seconds (15-86400)}
        {--secret= : Explicit Laravel maintenance bypass secret}
        {--with-secret : Generate a random Laravel maintenance bypass secret}';

    protected $description = 'Enable Planora maintenance mode with a branded pre-rendered 503 page';

    public function handle(): int
    {
        if (app()->isDownForMaintenance()) {
            $this->components->error('Planora is already in maintenance mode. Run planora:maintenance-up before changing its settings.');

            return self::FAILURE;
        }

        $retry = filter_var($this->option('retry'), FILTER_VALIDATE_INT);
        if ($retry === false || $retry < 15 || $retry > 86400) {
            $this->components->error('The retry interval must be between 15 and 86400 seconds.');

            return self::FAILURE;
        }

        if ($this->option('secret') !== null && $this->option('with-secret')) {
            $this->components->error('Choose either --secret or --with-secret, not both.');

            return self::FAILURE;
        }

        $message = $this->option('message') ?? config('planora.maintenance.message');
        if (trim($message) === '') {
            $this->components->error('The maintenance message cannot be empty.');

            return self::FAILURE;
        }
        if (mb_strlen($message) > 500) {
            $this->components->error('The maintenance message must be 500 characters or fewer.');

            return self::FAILURE;
        }

        $estimatedAt = $this->option('estimated-at') ?? config('planora.maintenance.estimated_at');
        $estimatedAtIso = null;
        if ($estimatedAt !== null && $estimatedAt !== '') {
            try {
                $date = CarbonImmutable::parse($estimatedAt);
                if ($date->isPast()) {
                    throw new \InvalidArgumentException();
                }
                $estimatedAt = $date->format('M j, Y g:i A T');
                $estimatedAtIso = $date->toIso8601String();
            } catch (\Throwable) {
                $this->components->error('Estimated availability must be a valid future date and time.');

                return self::FAILURE;
            }
        } else {
            $estimatedAt = null;
        }

        config([
            'planora.maintenance.message' => $message,
            'planora.maintenance.estimated_at' => $estimatedAt,
            'planora.maintenance.retry_after' => $retry,
        ]);

        $options = ['--render' => 'errors.503', '--retry' => (string) $retry, '--status' => 503];
        if ($this->option('secret') !== null) {
            $options['--secret'] = $this->option('secret');
        } elseif ($this->option('with-secret')) {
            $options['--with-secret'] = true;
        }

        $exitCode = $this->call('down', $options);

        if ($exitCode === self::SUCCESS && app()->isDownForMaintenance()) {
            $payload = app()->maintenanceMode()->data();
            $payload['planora'] = [
                'message' => $message,
                'estimated_at' => $estimatedAt,
                'estimated_at_iso' => $estimatedAtIso,
                'retry_after' => $retry,
            ];
            app()->maintenanceMode()->activate($payload);
        }

        return $exitCode;
    }
}
