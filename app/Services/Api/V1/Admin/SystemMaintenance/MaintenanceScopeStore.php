<?php

namespace App\Services\Api\V1\Admin\SystemMaintenance;

use RuntimeException;

class MaintenanceScopeStore
{
    public const SCOPES = ['public_website', 'authentication', 'dashboard_workspace'];

    public function read(): array
    {
        return $this->withLock(LOCK_SH, function ($stream): array {
            rewind($stream);
            $contents = stream_get_contents($stream);
            return $this->normalize($this->decode(is_string($contents) ? $contents : ''));
        });
    }

    public function set(string $scope, bool $enabled, array $preferences = []): array
    {
        if (! in_array($scope, self::SCOPES, true)) {
            throw new RuntimeException('Unknown maintenance scope.');
        }

        return $this->mutate(function (array $current) use ($scope, $enabled, $preferences): array {
            $current['scopes'][$scope] = $enabled;

            foreach (['message', 'estimated_at', 'estimated_at_iso', 'retry_after'] as $key) {
                if (array_key_exists($key, $preferences)) {
                    $current[$key] = $preferences[$key];
                }
            }

            return $current;
        });
    }

    public function setAll(bool $enabled, array $preferences = []): array
    {
        return $this->mutate(function (array $current) use ($enabled, $preferences): array {
            foreach (self::SCOPES as $scope) {
                $current['scopes'][$scope] = $enabled;
            }

            foreach (['message', 'estimated_at', 'estimated_at_iso', 'retry_after'] as $key) {
                if (array_key_exists($key, $preferences)) {
                    $current[$key] = $preferences[$key];
                }
            }

            return $current;
        });
    }

    public function updatePreferences(array $preferences): array
    {
        return $this->mutate(function (array $current) use ($preferences): array {
            foreach (['message', 'estimated_at', 'estimated_at_iso', 'retry_after'] as $key) {
                if (array_key_exists($key, $preferences)) {
                    $current[$key] = $preferences[$key];
                }
            }

            return $current;
        });
    }

    public function disableAll(): array
    {
        return $this->setAll(false);
    }

    public function reset(): array
    {
        return $this->mutate(fn (): array => [
            'scopes' => array_fill_keys(self::SCOPES, false),
            'message' => config('planora.maintenance.message'),
            'estimated_at' => config('planora.maintenance.estimated_at'),
            'estimated_at_iso' => null,
            'retry_after' => config('planora.maintenance.retry_after', 60),
        ]);
    }

    private function mutate(callable $callback): array
    {
        return $this->withLock(LOCK_EX, function ($stream) use ($callback): array {
            rewind($stream);
            $contents = stream_get_contents($stream);
            $next = $this->normalize($callback($this->normalize($this->decode(is_string($contents) ? $contents : ''))));
            $json = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            rewind($stream);
            if (! ftruncate($stream, 0) || fwrite($stream, $json) === false || ! fflush($stream)) {
                throw new RuntimeException('Maintenance preferences could not be stored.');
            }

            return $next;
        });
    }

    private function withLock(int $lock, callable $callback): mixed
    {
        $path = storage_path('framework/planora-maintenance-scopes.json');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Maintenance storage is unavailable.');
        }

        $stream = fopen($path, 'c+');
        if ($stream === false) {
            throw new RuntimeException('Maintenance storage is unavailable.');
        }

        try {
            if (! flock($stream, $lock)) {
                throw new RuntimeException('Maintenance storage is unavailable.');
            }

            return $callback($stream);
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
    }

    private function normalize(array $stored): array
    {
        $scopes = [];
        foreach (self::SCOPES as $scope) {
            $scopes[$scope] = ($stored['scopes'][$scope] ?? false) === true;
        }

        return [
            'scopes' => $scopes,
            'message' => is_string($stored['message'] ?? null) && trim($stored['message']) !== ''
                ? mb_substr($stored['message'], 0, 500)
                : config('planora.maintenance.message'),
            'estimated_at' => $stored['estimated_at'] ?? config('planora.maintenance.estimated_at'),
            'estimated_at_iso' => $stored['estimated_at_iso'] ?? null,
            'retry_after' => max(15, min(86400, (int) ($stored['retry_after'] ?? config('planora.maintenance.retry_after', 60)))),
        ];
    }

    private function decode(string $contents): array
    {
        if (trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (! is_array($decoded) || ! is_array($decoded['scopes'] ?? null)) {
            return ['scopes' => array_fill_keys(self::SCOPES, true)];
        }

        foreach (self::SCOPES as $scope) {
            if (! array_key_exists($scope, $decoded['scopes']) || ! is_bool($decoded['scopes'][$scope])) {
                return ['scopes' => array_fill_keys(self::SCOPES, true)];
            }
        }

        return $decoded;
    }
}
