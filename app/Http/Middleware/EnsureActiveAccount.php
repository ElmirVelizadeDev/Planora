<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

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
