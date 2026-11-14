<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $actualRole = $user?->role;

        if (! in_array($actualRole, \App\Models\User::ROLES, true)) {
            return $this->deny($request, 'Your account does not have an authorized role.');
        }

        $allowedRoles = count($roles) === 1 ? explode(',', $roles[0]) : $roles;
        if (! in_array($actualRole, $allowedRoles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This role cannot access this resource.'], 403);
            }

            return match ($actualRole) {
                'admin' => redirect('/admin/dashboard'),
                'moderator' => $user?->hasManagementPermission('users.view')
                    ? redirect('/admin/users')
                    : ($user?->hasManagementPermission('categories.view') ? redirect('/admin/categories')
                        : ($user?->hasManagementPermission('projects.view') ? redirect('/admin/projects')
                            : ($user?->hasManagementPermission('tasks.view') ? redirect('/admin/tasks')
                                : ($user?->hasManagementPermission('reports.view') ? redirect('/admin/reports')
                                    : ($user?->hasManagementPermission('activity.view') ? redirect('/admin/activity-logs') : redirect('/403')))))),
                'user' => redirect('/dashboard'),
                default => redirect('/403'),
            };
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        abort(403, $message);
    }
}
