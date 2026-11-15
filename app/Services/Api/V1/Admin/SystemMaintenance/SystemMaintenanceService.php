<?php

namespace App\Services\Api\V1\Admin\SystemMaintenance;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;

class SystemMaintenanceService
{
    public function status(): array
    {
        $active = app()->isDownForMaintenance();
        $metadata = [];

        if ($active) {
            try {
                $metadata = app()->maintenanceMode()->data()['planora'] ?? [];
            } catch (Throwable) {
                $metadata = [];
            }
        }

        return [
            'active' => $active,
            'message' => $metadata['message'] ?? config('planora.maintenance.message'),
            'estimated_at' => $metadata['estimated_at_iso'] ?? $metadata['estimated_at'] ?? config('planora.maintenance.estimated_at'),
            'retry_after' => (int) ($metadata['retry_after'] ?? config('planora.maintenance.retry_after', 60)),
        ];
    }

    public function enable(array $attributes): array
    {
        if (app()->isDownForMaintenance()) {
            throw new RuntimeException('Maintenance mode is already active.');
        }

        $options = [
            '--message' => $attributes['message'],
            '--retry' => (string) $attributes['retry_after'],
        ];

        if (! empty($attributes['estimated_at'])) {
            $options['--estimated-at'] = $attributes['estimated_at'];
        }

        if (Artisan::call('planora:maintenance-down', $options) !== 0 || ! app()->isDownForMaintenance()) {
            throw new RuntimeException('Maintenance mode could not be enabled.');
        }

        return $this->status();
    }

    public function disable(): array
    {
        if (! app()->isDownForMaintenance()) {
            return $this->status();
        }

        if (Artisan::call('planora:maintenance-up') !== 0 || app()->isDownForMaintenance()) {
            throw new RuntimeException('Maintenance mode could not be disabled.');
        }

        return $this->status();
    }
}
