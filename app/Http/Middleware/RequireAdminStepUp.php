<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminStepUp
{
    public function handle(Request $request, Closure $next, string $purpose): Response
    {
        if ($purpose === 'settings.security' && $request->route('group') !== 'security') return $next($request);
        if ($purpose === 'roles.manage' && $request->isMethod('POST') && $request->is('api/v1/admin/users')
            && ! in_array($request->input('role'), ['admin', 'moderator'], true)) return $next($request);
        $user = $request->user();
        if ($user?->role === 'moderator') return $next($request);
        $hasSession = $request->hasSession();
        $grant = $hasSession ? $request->session()->get('admin_step_up_grant') : null;
        $valid = $user && $user->role === 'admin' && is_array($grant)
            && ($grant['purpose'] ?? null) === $purpose
            && (int) ($grant['user_id'] ?? 0) === (int) $user->id
            && (int) ($grant['expires_at'] ?? 0) >= now()->timestamp
            && hash_equals((string) ($grant['session_hash'] ?? ''), hash('sha256', $request->session()->getId()));

        if (! $valid) {
            if ($hasSession) $request->session()->forget('admin_step_up_grant');
            return response()->json(['message' => 'Recent identity verification is required.', 'step_up_required' => true, 'purpose' => $purpose], 428);
        }

        $request->session()->forget('admin_step_up_grant');
        return $next($request);
    }
}
