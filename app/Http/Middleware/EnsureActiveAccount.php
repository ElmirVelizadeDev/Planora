<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'admin' && ! Auth::guard('web')->check()) {
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->expectsJson()) return response()->json(['message' => 'Administrator access requires an MFA-verified browser session.'], 403);
            abort(403, 'Administrator access requires an MFA-verified browser session.');
        }

        if ($user && $user->role === 'admin' && $user->mfa_enabled_at === null) {
            if (Auth::guard('web')->check()) Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->expectsJson()) return response()->json(['message' => 'Administrator MFA enrollment is required.'], 403);
            return redirect()->guest('/login');
        }

        $webGuard = Auth::guard('web');
        if ($user && $request->hasSession() && $webGuard->check()) {
            $sessionVersion = $request->session()->get('auth_version_web');
            if ($sessionVersion === null && $webGuard->viaRemember()) {
                $sessionVersion = (int) ($user->auth_version ?? 1);
                $request->session()->put('auth_version_web', $sessionVersion);
            }
            $invalidVersion = ! is_numeric($sessionVersion) || (int) $sessionVersion !== (int) ($user->auth_version ?? 1);
            if ($invalidVersion) {
                $webGuard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Your session is no longer valid. Sign in again.'], 401);
                }
                return redirect()->guest('/login');
            }
        }

        if ($user && $user->status !== 'active') {
            $message = match ($user->status) {
                'suspended' => 'This account is temporarily suspended.',
                'banned' => 'This account is banned.',
                default => 'This account is inactive.',
            };

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'status' => $user->status], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
