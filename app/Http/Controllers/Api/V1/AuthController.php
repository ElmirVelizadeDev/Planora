<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Api\V1\Auth\RegistrationService;
use App\Services\Api\V1\Admin\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request, UserActivityLogger $activityLogger): JsonResponse
    {
        $guard = Auth::guard('web');

        if (! $guard->attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $user = $guard->user();

        if (! in_array($user->role, \App\Models\User::ROLES, true) || $user->status !== 'active') {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'This account is not authorized to sign in.'], 403);
        }

        $user->last_login_at = now();
        $user->save();
        $activityLogger->record('auth.login', 'User signed in.', $user, $user);
        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'redirect_to' => $this->dashboardForUser($user),
        ]);
    }

    public function register(RegisterRequest $request, RegistrationService $registration): JsonResponse
    {
        $user = $registration->register($request->validated());
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'redirect_to' => '/dashboard',
        ], 201);
    }

    public function logout(Request $request, UserActivityLogger $activityLogger): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $activityLogger->record('auth.logout', 'User signed out.', $user, $user);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    private function dashboardForUser(\App\Models\User $user): string
    {
        return match ($user->role) {
            'admin' => '/admin/dashboard',
            'user' => '/dashboard',
            'moderator' => in_array('users.view', $user->moderator_permissions ?? [], true)
                ? '/admin/users'
                : (in_array('activity.view', $user->moderator_permissions ?? [], true) ? '/admin/users/activity' : '/403'),
            default => '/403',
        };
    }
}
