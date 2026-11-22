<?php

namespace App\Http\Middleware;

use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;
use Closure;
use Illuminate\Http\Request;

class EnforceMaintenanceScope
{
    public function handle(Request $request, Closure $next, string $scope)
    {
        if (! in_array($scope, MaintenanceScopeStore::SCOPES, true)) {
            abort(404);
        }

        $maintenance = app(MaintenanceScopeStore::class)->read();
        if (! $maintenance['scopes'][$scope]) {
            return $next($request);
        }

        $guard = $request->is('api/*') ? 'sanctum' : 'web';
        if ($scope === 'dashboard_workspace' && $request->user($guard)?->role === 'admin') {
            return $next($request);
        }

        $area = match ($scope) {
            'public_website' => 'Public website',
            'authentication' => 'Authentication',
            default => 'Dashboard and workspace',
        };
        $message = $maintenance['message'];
        $retryAfter = (int) $maintenance['retry_after'];

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'scope' => $scope,
                'area' => $area,
            ], 503)->header('Retry-After', (string) $retryAfter);
        }

        return response()->view('errors.page', [
            'code' => 503,
            'title' => $area.' temporarily unavailable',
            'description' => $message,
            'estimatedAt' => $maintenance['estimated_at'],
            'retryAfter' => $retryAfter,
            'maintenanceScopes' => $maintenance['scopes'],
        ], 503)->header('Retry-After', (string) $retryAfter);
    }
}
