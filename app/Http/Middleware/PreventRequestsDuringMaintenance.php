<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;
use Closure;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array<int, string>
     */
    protected $except = [
        'admin/system-maintenance',
        'api/v1/admin/system-maintenance*',
        'api/v1/user',
    ];

    public function handle($request, Closure $next)
    {
        try {
            $response = parent::handle($request, $next);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 503 || ! $this->isApiRequest($request)) {
                throw $exception;
            }

            return $this->apiMaintenanceResponse();
        }

        if ($response->getStatusCode() === 503 && $this->isApiRequest($request)) {
            return $this->apiMaintenanceResponse($response->headers->get('Retry-After'));
        }

        return $response;
    }

    private function isApiRequest($request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private function apiMaintenanceResponse(?string $retryAfter = null)
    {
        $settings = $this->maintenanceSettings();
        $response = response()->json([
            'message' => $settings['message'],
        ], 503);

        $response->headers->set('Retry-After', (string) ($retryAfter ?? $settings['retry_after']));

        return $response;
    }

    private function maintenanceSettings(): array
    {
        try {
            $payload = app()->maintenanceMode()->data();
            $metadata = $payload['planora'] ?? [];
        } catch (\Throwable) {
            $payload = [];
            $metadata = [];
        }

        return [
            'message' => (string) ($metadata['message'] ?? config('planora.maintenance.message')),
            'retry_after' => (int) ($metadata['retry_after'] ?? $payload['retry'] ?? config('planora.maintenance.retry_after', 60)),
        ];
    }
}
