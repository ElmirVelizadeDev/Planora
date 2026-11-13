<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResendRegistrationOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyRegistrationOtpRequest;
use App\Http\Resources\Api\V1\Shared\Users\UserResource;
use App\Services\Api\V1\Auth\RegistrationService;
use App\Services\Api\V1\Admin\UserActivityLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private static ?string $dummyPasswordHash = null;

    public function login(LoginRequest $request, UserActivityLogger $activityLogger): JsonResponse
    {
        $guard = Auth::guard('web');
        $guard->setRememberDuration((int) config('session.remember_lifetime', 43200));

        // Remove a prior account's session or recaller before switching identities.
        if ($guard->check() || $request->cookies->has($guard->getRecallerName())) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $user = \App\Models\User::query()->where('email', $request->validated('email'))->first();
        $passwordHash = $user?->getAuthPassword() ?? (self::$dummyPasswordHash ??= Hash::make(Str::random(48)));
        if (! $user || ! Hash::check($request->validated('password'), $passwordHash)
            || ! in_array($user->role, \App\Models\User::ROLES, true)
            || $user->status !== 'active'
            || ! $user->email_verified_at) {
            // Record the security outcome without persisting the submitted email or password.
            $activityLogger->record('auth.login_failed', 'A sign-in attempt was rejected.', status: 'failure');
            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        $guard->login($user, $request->boolean('remember'));
        $user->last_login_at = now();
        $user->save();
        $activityLogger->record('auth.login', 'User signed in.', $user, $user);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->getAuthPassword());

        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'redirect_to' => $this->dashboardForUser($user),
        ]);
    }

    public function register(RegisterRequest $request, RegistrationService $registration, SettingsService $settings): JsonResponse
    {
        $databaseOtp = $registration->begin($request->validated());
        $policy = $settings->passwordPolicy();

        return response()->json([
            'message' => $databaseOtp
                ? 'If this email can be registered, a local verification code is available.'
                : 'If this email can be registered, a verification code will be sent.',
            'email' => $request->validated('email'),
            'expires_in' => (int) ($policy['otp_expiration_minutes'] ?? 5) * 60,
            'resend_in' => (int) ($policy['otp_resend_cooldown_seconds'] ?? 60),
            'delivery_mode' => $databaseOtp ? 'database' : 'email',
        ], 202);
    }

    public function verifyRegistration(VerifyRegistrationOtpRequest $request, RegistrationService $registration): JsonResponse
    {
        $user = $registration->verify($request->validated('email'), $request->validated('code'));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->getAuthPassword());

        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'redirect_to' => '/dashboard',
        ]);
    }

    public function resendRegistrationCode(ResendRegistrationOtpRequest $request, RegistrationService $registration, SettingsService $settings): JsonResponse
    {
        $databaseOtp = $registration->resend($request->validated('email'));
        $policy = $settings->passwordPolicy();

        return response()->json([
            'message' => $databaseOtp
                ? 'If a pending registration exists, a new local verification code is available.'
                : 'If a pending registration exists, a new verification code will be sent.',
            'expires_in' => (int) ($policy['otp_expiration_minutes'] ?? 5) * 60,
            'resend_in' => (int) ($policy['otp_resend_cooldown_seconds'] ?? 60),
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
                : (in_array('activity.view', $user->moderator_permissions ?? [], true) ? '/admin/activity-logs' : '/403'),
            default => '/403',
        };
    }
}
