<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResendRegistrationOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyRegistrationOtpRequest;
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
        $guard->setRememberDuration((int) config('session.remember_lifetime', 43200));

        // Remove a prior account's session or recaller before switching identities.
        if ($guard->check() || $request->cookies->has($guard->getRecallerName())) {
            $guard->logout();
        }

        if (! $guard->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
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

        if (! $user->email_verified_at) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Verify your email address before signing in.'], 403);
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
        $databaseOtp = $registration->begin($request->validated());

        return response()->json([
            'message' => $databaseOtp
                ? 'A verification code is ready in the development database.'
                : 'A verification code has been sent to your email address.',
            'email' => $request->validated('email'),
            'expires_in' => 300,
            'resend_in' => 60,
            'delivery_mode' => $databaseOtp ? 'database' : 'email',
        ], 202);
    }

    public function verifyRegistration(VerifyRegistrationOtpRequest $request, RegistrationService $registration): JsonResponse
    {
        $user = $registration->verify($request->validated('email'), $request->validated('code'));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'redirect_to' => '/dashboard',
        ]);
    }

    public function resendRegistrationCode(ResendRegistrationOtpRequest $request, RegistrationService $registration): JsonResponse
    {
        $databaseOtp = $registration->resend($request->validated('email'));

        return response()->json([
            'message' => $databaseOtp
                ? 'A new verification code is ready in the development database.'
                : 'A new verification code has been sent.',
            'expires_in' => 300,
            'resend_in' => 60,
            'delivery_mode' => $databaseOtp ? 'database' : 'email',
        ]);
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
