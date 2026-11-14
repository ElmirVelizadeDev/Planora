<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireManagementAccess
{
    public function handle(Request $request, Closure $next, string $requiredPermission = ''): Response
    {
        $user = $request->user();
        $path = (string) $request->route('path', '');
        $permission = $requiredPermission ?: (str_starts_with($path, 'activity') ? 'activity.view' : 'users.view');

        if ($user?->role === 'admin' || $user?->hasManagementPermission($permission)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You do not have permission to access this user management section.'], 403);
        }

        abort(403, 'You do not have permission to access this user management section.');
    }
}
