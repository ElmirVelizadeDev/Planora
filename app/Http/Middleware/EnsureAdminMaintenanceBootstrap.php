<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminMaintenanceBootstrap
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->isDownForMaintenance() && $request->user()?->role !== 'admin') {
            try {
                $payload = app()->maintenanceMode()->data();
                $metadata = $payload['planora'] ?? [];
            } catch (\Throwable) {
                $payload = [];
                $metadata = [];
            }

            return response()->json([
                'message' => (string) ($metadata['message'] ?? config('planora.maintenance.message')),
            ], 503)->header('Retry-After', (string) ($metadata['retry_after'] ?? $payload['retry'] ?? config('planora.maintenance.retry_after', 60)));
        }

        return $next($request);
    }
}
