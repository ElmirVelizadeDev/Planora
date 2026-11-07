<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireManagementAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $path = (string) $request->route('path', '');
        $permission = str_starts_with($path, 'activity') ? 'activity.view' : 'users.view';

        if ($user?->hasManagementPermission($permission)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You do not have permission to access this user management section.'], 403);
        }

        abort(403, 'You do not have permission to access this user management section.');
    }
}
