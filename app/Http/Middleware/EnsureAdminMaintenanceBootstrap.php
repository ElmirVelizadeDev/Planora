<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminMaintenanceBootstrap
{
    public function handle(Request $request, Closure $next)
    {
        $guard = $request->is('api/*') ? 'sanctum' : 'web';

        if (app()->isDownForMaintenance() && $request->user($guard)?->role !== 'admin') {
            try {
                $payload = app()->maintenanceMode()->data();
                $metadata = $payload['planora'] ?? [];
            } catch (\Throwable) {
                $payload = [];
                $metadata = [];
            }

            $message = (string) ($metadata['message'] ?? config('planora.maintenance.message'));
            $retryAfter = (int) ($metadata['retry_after'] ?? $payload['retry'] ?? config('planora.maintenance.retry_after', 60));

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $message], 503)
                    ->header('Retry-After', (string) $retryAfter);
            }

            return response()->view('errors.page', [
                'code' => 503,
                'title' => 'We will be back soon',
                'description' => $message,
                'estimatedAt' => $metadata['estimated_at_iso'] ?? $metadata['estimated_at'] ?? config('planora.maintenance.estimated_at'),
                'retryAfter' => $retryAfter,
            ], 503)->header('Retry-After', (string) $retryAfter);
        }

        return $next($request);
    }
}
