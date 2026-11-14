<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && ($user->role === 'admin'
            || $user->hasManagementPermission('users.view')
            || $user->hasManagementPermission('activity.view')
            || $user->hasManagementPermission('categories.view')
            || $user->hasManagementPermission('projects.view')
            || $user->hasManagementPermission('tasks.view')
            || $user->hasManagementPermission('reports.view')), 403);

        return $next($request);
    }
}
