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
use App\Services\Api\V1\Admin\AdminMfaService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private static ?string $dummyPasswordHash = null;

    public function login(LoginRequest $request, UserActivityLogger $activityLogger, AdminMfaService $mfa): JsonResponse
    {
        return $this->authenticate($request, $activityLogger, null, $mfa);
    }

    public function adminLoginPage(): Response
    {
        return response()->view('welcome', ['appView' => 'admin-auth'])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function adminLogin(LoginRequest $request, UserActivityLogger $activityLogger, AdminMfaService $mfa): JsonResponse
    {
        return $this->authenticate($request, $activityLogger, 'admin', $mfa);
    }

    private function authenticate(LoginRequest $request, UserActivityLogger $activityLogger, ?string $requiredRole, AdminMfaService $mfa): JsonResponse
    {
        $guard = Auth::guard('web');
        $guard->setRememberDuration((int) config('session.remember_lifetime', 43200));

        $user = \App\Models\User::query()->where('email', $request->validated('email'))->first();
        $passwordHash = $user?->getAuthPassword() ?? (self::$dummyPasswordHash ??= Hash::make(Str::random(48)));
        if (! $user || ! Hash::check($request->validated('password'), $passwordHash)
            || ! in_array($user->role, \App\Models\User::ROLES, true)
            || ($requiredRole !== null && $user->role !== $requiredRole)
            || $user->status !== 'active'
            || ! $user->email_verified_at) {
            // Record the security outcome without persisting the submitted email or password.
            $activityLogger->record('auth.login_failed', 'A sign-in attempt was rejected.', status: 'failure');
            $request->session()->forget('admin_mfa_login_challenge');
            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        if ($user->role === 'admin' && $mfa->isEnabled($user)) {
            if ($guard->check() || $request->cookies->has($guard->getRecallerName())) $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->regenerate();
            $request->session()->put('admin_mfa_login_challenge', [
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(5)->timestamp,
                'remember' => $request->boolean('remember'),
            ]);
            $activityLogger->record('auth.admin_mfa_challenge_started', 'An administrator password was accepted; second-factor verification is required.', $user, null, status: 'success');
            return response()->json(['mfa_required' => true, 'expires_in' => 300])->header('Cache-Control', 'no-store, private');
        }

        // Revoke an existing identity only after the replacement credentials are valid.
        if ($guard->check() || $request->cookies->has($guard->getRecallerName())) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $guard->login($user, $request->boolean('remember'));
        $user->last_login_at = now();
        $user->save();
        $activityLogger->record('auth.login', 'User signed in.', $user, $user);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->getAuthPassword());

        $response = response()->json([
            'user' => UserResource::make($user)->resolve(),
            'redirect_to' => $requiredRole === 'admin' ? '/admin/dashboard' : $this->dashboardForUser($user),
        ]);

        if ($requiredRole === 'admin') {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }

    public function verifyAdminMfa(\App\Http\Requests\Api\V1\Auth\VerifyAdminMfaChallengeRequest $request, AdminMfaService $mfa, UserActivityLogger $activityLogger): JsonResponse
    {
        $challenge = $request->session()->get('admin_mfa_login_challenge');
        if (! is_array($challenge) || (int) ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('admin_mfa_login_challenge');
            return response()->json(['message' => 'The administrator verification challenge has expired. Sign in again.'], 401);
        }

        $user = \App\Models\User::query()->find($challenge['user_id'] ?? 0);
        if (! $user || $user->role !== 'admin' || $user->status !== 'active' || ! $user->email_verified_at || ! $mfa->isEnabled($user)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return response()->json(['message' => 'The administrator verification challenge is no longer valid. Sign in again.'], 401);
        }

        $usedRecoveryCode = ! $request->validated('code');
        $valid = ! $usedRecoveryCode
            ? $mfa->verifyTotp($user, $request->validated('code'))
            : $mfa->consumeRecoveryCode($user, $request->validated('recovery_code'));
        if (! $valid) {
            $activityLogger->record('auth.admin_mfa_failed', 'Administrator second-factor verification failed.', $user, null, status: 'failure');
            return response()->json(['message' => 'The verification code is invalid or has already been used.'], 422);
        }

        $request->session()->forget('admin_mfa_login_challenge');
        $guard = Auth::guard('web');
        $guard->setRememberDuration((int) config('session.remember_lifetime', 43200));
        $guard->login($user, (bool) ($challenge['remember'] ?? false));
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->getAuthPassword());
        $activityLogger->record('auth.admin_mfa_verified', 'Administrator completed second-factor verification.', $user, $user);
        if ($usedRecoveryCode) $activityLogger->record('security.mfa_recovery_used', 'Administrator used a single-use MFA recovery code.', $user, $user, module: 'Security');
        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'redirect_to' => '/admin/dashboard',
        ])->header('Cache-Control', 'no-store, private');
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
