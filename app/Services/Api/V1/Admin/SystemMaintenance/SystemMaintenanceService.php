<?php

namespace App\Services\Api\V1\Admin\SystemMaintenance;

use Illuminate\Support\Facades\Artisan;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

class SystemMaintenanceService
{
    public function __construct(private readonly MaintenanceScopeStore $scopes)
    {
    }

    public function status(): array
    {
        $nativeActive = app()->isDownForMaintenance();
        $settings = $this->scopes->read();
        $nativeMetadata = [];

        if ($nativeActive) {
            try {
                $nativeMetadata = app()->maintenanceMode()->data()['planora'] ?? [];
            } catch (Throwable) {
                $nativeMetadata = [];
            }
        }

        $configuredCount = count(array_filter($settings['scopes']));
        $active = $nativeActive || $configuredCount > 0;
        $overall = $nativeActive || $configuredCount === count(MaintenanceScopeStore::SCOPES)
            ? 'full_maintenance'
            : ($active ? 'partially_under_maintenance' : 'operational');
        $effectiveScopes = [];
        foreach ($settings['scopes'] as $scope => $enabled) {
            $effectiveScopes[$scope] = $nativeActive || $enabled;
        }

        return [
            'active' => $active,
            'native_active' => $nativeActive,
            'overall' => $overall,
            'scopes' => $settings['scopes'],
            'effective_scopes' => $effectiveScopes,
            'message' => $nativeMetadata['message'] ?? $settings['message'],
            'estimated_at' => $nativeMetadata['estimated_at_iso'] ?? $nativeMetadata['estimated_at'] ?? $settings['estimated_at_iso'] ?? $settings['estimated_at'],
            'retry_after' => (int) ($nativeMetadata['retry_after'] ?? $settings['retry_after']),
        ];
    }

    public function enable(array $attributes): array
    {
        if (app()->isDownForMaintenance()) {
            throw new RuntimeException('Laravel global maintenance is active. Disable it with planora:maintenance-up before changing scopes.');
        }

        $this->scopes->setAll(true, $this->preferences($attributes));

        return $this->status();
    }

    public function updateScope(string $scope, array $attributes): array
    {
        if (app()->isDownForMaintenance()) {
            throw new RuntimeException('Laravel global maintenance is active. Disable it with planora:maintenance-up before changing scopes.');
        }

        $this->scopes->set($scope, (bool) $attributes['enabled'], $this->preferences($attributes));

        return $this->status();
    }

    public function updatePreferences(array $attributes): array
    {
        if (app()->isDownForMaintenance()) {
            throw new RuntimeException('Laravel global maintenance is active. Disable it with planora:maintenance-up before changing scope preferences.');
        }

        $this->scopes->updatePreferences($this->preferences($attributes));

        return $this->status();
    }

    public function disable(): array
    {
        $this->scopes->disableAll();

        if (Artisan::call('planora:maintenance-up') !== 0 || app()->isDownForMaintenance()) {
            throw new RuntimeException('Maintenance mode could not be disabled.');
        }

        return $this->status();
    }

    private function preferences(array $attributes): array
    {
        $preferences = [];

        if (array_key_exists('message', $attributes)) {
            $preferences['message'] = $attributes['message'];
        }
        if (array_key_exists('estimated_at', $attributes)) {
            $estimatedAt = $attributes['estimated_at'];
            $date = $estimatedAt ? CarbonImmutable::parse($estimatedAt) : null;
            $preferences['estimated_at'] = $date?->format('M j, Y g:i A T');
            $preferences['estimated_at_iso'] = $date?->toIso8601String();
        }
        if (array_key_exists('retry_after', $attributes)) $preferences['retry_after'] = $attributes['retry_after'];

        return $preferences;
    }
}
