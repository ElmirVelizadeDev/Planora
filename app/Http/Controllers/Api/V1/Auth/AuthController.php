<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResendRegistrationOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyRegistrationOtpRequest;
use App\Http\Resources\Api\V1\Shared\Users\UserResource;
use App\Services\Api\V1\Auth\RegistrationService;
use App\Services\Api\V1\Shared\UserActivityLogger;
use App\Services\Api\V1\Admin\AdminMfaService;
use App\Services\Api\V1\Admin\AdminMfaAttemptService;
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
        if (! $request->hasSession()) {
            $activityLogger->record('auth.login_failed', 'A sign-in attempt was rejected.', status: 'failure');
            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        $request->session()->forget(['admin_mfa_login_challenge', 'admin_mfa_enrollment_challenge']);
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

        if ($user->role === 'admin') {
            if ($guard->check() || $request->cookies->has($guard->getRecallerName())) $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->regenerate();
            $key = $mfa->isEnabled($user) ? 'admin_mfa_login_challenge' : 'admin_mfa_enrollment_challenge';
            $request->session()->put($key, [
                'user_id' => $user->id,
                'auth_version' => (int) ($user->auth_version ?? 1),
                'challenge_id' => Str::random(64),
                'expires_at' => now()->addMinutes(max(1, (int) config('auth.admin_mfa.'.($mfa->isEnabled($user) ? 'login_challenge_minutes' : 'enrollment_challenge_minutes'), 5)))->timestamp,
                'remember' => $request->boolean('remember'),
            ]);
            $activityLogger->record($mfa->isEnabled($user) ? 'auth.admin_mfa_challenge_started' : 'auth.admin_mfa_enrollment_required', 'Administrator password accepted; mandatory second-factor setup or verification is required.', $user, null, status: 'success');
            return response()->json($mfa->isEnabled($user)
                ? ['mfa_required' => true, 'expires_in' => max(1, (int) config('auth.admin_mfa.login_challenge_minutes', 5)) * 60]
                : ['mfa_enrollment_required' => true, 'expires_in' => max(1, (int) config('auth.admin_mfa.enrollment_challenge_minutes', 10)) * 60])
                ->header('Cache-Control', 'no-store, private');
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
        $request->session()->put('auth_version_web', (int) ($user->auth_version ?? 1));

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

    public function verifyAdminMfa(\App\Http\Requests\Api\V1\Auth\VerifyAdminMfaChallengeRequest $request, AdminMfaService $mfa, UserActivityLogger $activityLogger, AdminMfaAttemptService $attempts): JsonResponse
    {
        $challenge = $request->session()->get('admin_mfa_login_challenge');
        if (! is_array($challenge) || (int) ($challenge['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget('admin_mfa_login_challenge');
            return response()->json(['message' => 'The administrator verification challenge has expired. Sign in again.'], 401);
        }

        $user = \App\Models\User::query()->find($challenge['user_id'] ?? 0);
        if (! $user || (int) ($challenge['auth_version'] ?? 0) !== (int) ($user->auth_version ?? 1)
            || $user->role !== 'admin' || $user->status !== 'active' || ! $user->email_verified_at || ! $mfa->isEnabled($user)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return response()->json(['message' => 'The administrator verification challenge is no longer valid. Sign in again.'], 401);
        }

        $usedRecoveryCode = ! $request->validated('code');
        $result = $attempts->verify($user, 'login', (string) ($challenge['challenge_id'] ?? ''), fn () => ! $usedRecoveryCode
            ? $mfa->verifyTotp($user, $request->validated('code'))
            : $mfa->consumeRecoveryCode($user, $request->validated('recovery_code')));
        if ($result['status'] === 'locked') {
            $request->session()->forget('admin_mfa_login_challenge');
            $activityLogger->record('auth.admin_mfa_locked', 'Repeated administrator MFA failures triggered a temporary lockout.', $user, null, status: 'failure');
            return response()->json(['message' => 'Verification is temporarily locked. Sign in again later.'], 429)->header('Retry-After', (string) ($result['retry_after'] ?? 300));
        }
        if ($result['status'] !== 'verified') {
            $activityLogger->record('auth.admin_mfa_failed', 'Administrator second-factor verification failed.', $user, null, status: 'failure');
            return response()->json(['message' => 'The verification code is invalid or has already been used.'], 422);
        }

        $request->session()->forget('admin_mfa_login_challenge');
        $guard = Auth::guard('web');
        $guard->setRememberDuration((int) config('session.remember_lifetime', 43200));
        $guard->login($user, (bool) ($challenge['remember'] ?? false));
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->getAuthPassword());
        $request->session()->put('auth_version_web', (int) ($user->auth_version ?? 1));
        $user->forceFill(['last_login_at' => now()])->save();
        $activityLogger->record('auth.admin_mfa_verified', 'Administrator completed second-factor verification.', $user, $user);
        if ($usedRecoveryCode) $activityLogger->record('security.mfa_recovery_used', 'Administrator used a single-use MFA recovery code.', $user, $user, module: 'Security');
        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'redirect_to' => '/admin/dashboard',
        ])->header('Cache-Control', 'no-store, private');
    }

    public function register(RegisterRequest $request, RegistrationService $registration, SettingsService $settings): JsonResponse
    {
        $startedAt = hrtime(true);
        $databaseOtp = $registration->begin($request->validated());
        $policy = $settings->passwordPolicy();
        $this->padEmailResponse($startedAt);

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
        $request->session()->put('auth_version_web', (int) ($user->auth_version ?? 1));

        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'redirect_to' => '/dashboard',
        ]);
    }

    public function resendRegistrationCode(ResendRegistrationOtpRequest $request, RegistrationService $registration, SettingsService $settings): JsonResponse
    {
        $startedAt = hrtime(true);
        $databaseOtp = $registration->resend($request->validated('email'));
        $policy = $settings->passwordPolicy();
        $this->padEmailResponse($startedAt);

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
            'moderator' => match (true) {
                in_array('users.view', $user->moderator_permissions ?? [], true) => '/admin/users',
                in_array('categories.view', $user->moderator_permissions ?? [], true) => '/admin/categories',
                in_array('projects.view', $user->moderator_permissions ?? [], true) => '/admin/projects',
                in_array('tasks.view', $user->moderator_permissions ?? [], true) => '/admin/tasks',
                in_array('reports.view', $user->moderator_permissions ?? [], true) => '/admin/reports',
                in_array('activity.view', $user->moderator_permissions ?? [], true) => '/admin/activity-logs',
                default => '/403',
            },
            default => '/403',
        };
    }

    private function padEmailResponse(int $startedAt): void
    {
        if (app()->environment('testing')) return;
        $minimum = max(0, (int) config('auth.email_enumeration_min_response_ms', 250));
        $remainingNs = ($minimum * 1_000_000) - (hrtime(true) - $startedAt);
        if ($remainingNs > 0) usleep((int) ceil($remainingNs / 1000));
    }
}
