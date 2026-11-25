<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\VerifyAdminStepUpRequest;
use App\Models\User;
use App\Services\Api\V1\Admin\AdminMfaService;
use App\Services\Api\V1\Admin\AdminMfaAttemptService;
use App\Services\Api\V1\Admin\AdminMfaQrCodeService;
use App\Services\Api\V1\Shared\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminMfaController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json(['enabled' => $user->mfa_enabled_at !== null, 'enabled_at' => $user->mfa_enabled_at]);
    }

    public function begin(Request $request, AdminMfaService $mfa, AdminMfaQrCodeService $qrCode, UserActivityLogger $logger): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:128']]);
        $user = $request->user();
        if ($user->mfa_enabled_at) return response()->json(['message' => 'MFA is already enabled.'], 409);
        if (! $mfa->verifyPassword($user, $data['password'])) throw ValidationException::withMessages(['password' => ['Password verification failed.']]);

        $setup = $mfa->beginEnrollment($user);
        $logger->record('security.mfa_enrollment_started', 'Administrator began MFA enrollment.', $user, $user, module: 'Security');
        try {
            $setup['qr_code_data_uri'] = $qrCode->toPngDataUri($setup['otpauth_uri']);
        } catch (Throwable $exception) {
            Log::error('Administrator MFA QR generation failed.', [
                'admin_id' => $user->id,
                'exception_type' => get_class($exception),
            ]);

            return response()->json(['message' => 'MFA setup could not be prepared. Please try again.'], 500)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->json(['qr_code_data_uri' => $setup['qr_code_data_uri'], 'expires_at' => $setup['expires_at']])->header('Cache-Control', 'no-store, private');
    }

    public function enable(Request $request, AdminMfaService $mfa, UserActivityLogger $logger): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $user = $request->user();
        $codes = $mfa->enable($user, $data['code']);
        if (! $codes) {
            $logger->record('security.mfa_enrollment_failed', 'Administrator MFA enrollment verification failed.', $user, $user, status: 'failure', module: 'Security');
            throw ValidationException::withMessages(['code' => ['The code is invalid or the enrollment has expired. Start enrollment again.']]);
        }
        $logger->record('security.mfa_enabled', 'Administrator enabled MFA.', $user, $user, module: 'Security');
        return response()->json(['enabled' => true, 'recovery_codes' => $codes])->header('Cache-Control', 'no-store, private');
    }

    public function cancelEnrollment(Request $request, UserActivityLogger $logger): JsonResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $locked->mfa_enabled_at) $locked->forceFill(['mfa_secret' => null, 'mfa_enrollment_expires_at' => null])->save();
        });
        $logger->record('security.mfa_enrollment_cancelled', 'Administrator cancelled MFA enrollment.', $user, $user, module: 'Security');
        return response()->json(['cancelled' => true]);
    }

    public function disable(Request $request, AdminMfaService $mfa, UserActivityLogger $logger): JsonResponse
    {
        return response()->json(['message' => 'MFA is mandatory for administrator accounts.'], 409);
    }

    public function regenerateRecoveryCodes(Request $request, AdminMfaService $mfa, UserActivityLogger $logger): JsonResponse
    {
        $user = $request->user();
        if (! $user->mfa_enabled_at) return response()->json(['message' => 'MFA is not enabled.'], 409);
        $codes = $mfa->regenerateRecoveryCodes($user);
        $logger->record('security.mfa_recovery_regenerated', 'Administrator regenerated MFA recovery codes.', $user, $user, module: 'Security');
        return response()->json(['recovery_codes' => $codes])->header('Cache-Control', 'no-store, private');
    }

    public function verifyStepUp(VerifyAdminStepUpRequest $request, AdminMfaService $mfa, UserActivityLogger $logger, AdminMfaAttemptService $attempts): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json(['message' => 'A first-party administrator session is required for this verification.'], 409);
        }

        $user = $request->user();
        $data = $request->validated();
        abort_unless($mfa->isEnabled($user), 403, 'Administrator MFA is required.');
        $usedRecoveryCode = ! isset($data['code']) && isset($data['recovery_code']);
        $result = $attempts->verify($user, 'step-up', (string) $request->session()->getId(), fn () => isset($data['code'])
            ? $mfa->verifyTotp($user, $data['code'])
            : ($usedRecoveryCode ? $mfa->consumeRecoveryCode($user, $data['recovery_code']) : false));
        if ($result['status'] === 'locked') {
            $request->session()->forget('admin_step_up_grant');
            $request->session()->regenerate(true);
            return response()->json(['message' => 'Verification is temporarily locked. Try again later.'], 429)->header('Retry-After', (string) ($result['retry_after'] ?? 300));
        }
        $valid = $result['status'] === 'verified';

        if (! $valid) {
            $logger->record('security.step_up_failed', 'Administrator step-up verification failed.', $user, $user, ['purpose' => $data['purpose']], 'Security', 'failure');
            throw ValidationException::withMessages(['code' => ['Identity verification failed.']]);
        }

        $request->session()->put('admin_step_up_grant', [
            'purpose' => $data['purpose'], 'expires_at' => now()->addSeconds(max(1, (int) config('auth.admin_mfa.step_up_grant_seconds', 60)))->timestamp,
            'user_id' => $user->id, 'session_hash' => hash('sha256', $request->session()->getId()),
        ]);
        $logger->record('security.step_up_succeeded', 'An authorized operator completed step-up verification.', $user, $user, ['purpose' => $data['purpose']], 'Security');
        if ($usedRecoveryCode) $logger->record('security.mfa_recovery_used', 'Administrator used a single-use MFA recovery code for step-up.', $user, $user, module: 'Security');
        return response()->json(['verified' => true, 'expires_in' => max(1, (int) config('auth.admin_mfa.step_up_grant_seconds', 60))])->header('Cache-Control', 'no-store, private');
    }

    public function beginLoginEnrollment(Request $request, AdminMfaService $mfa, AdminMfaQrCodeService $qrCode): JsonResponse
    {
        $challenge = $request->session()->get('admin_mfa_enrollment_challenge');
        $user = $this->enrollmentUser($challenge);
        $setup = $mfa->beginEnrollment($user);
        try { $qr = $qrCode->toPngDataUri($setup['otpauth_uri']); }
        catch (Throwable $exception) {
            Log::error('Administrator MFA QR generation failed.', ['admin_id' => $user->id, 'exception_type' => get_class($exception)]);
            return response()->json(['message' => 'MFA setup could not be prepared. Please try again.'], 500)->header('Cache-Control', 'no-store, private');
        }
        return response()->json(['qr_code_data_uri' => $qr, 'expires_at' => $setup['expires_at']])->header('Cache-Control', 'no-store, private');
    }

    public function verifyLoginEnrollment(Request $request, AdminMfaService $mfa, AdminMfaAttemptService $attempts, UserActivityLogger $logger): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $challenge = $request->session()->get('admin_mfa_enrollment_challenge');
        $user = $this->enrollmentUser($challenge);
        $result = $attempts->verify($user, 'enroll-login', (string) ($challenge['challenge_id'] ?? ''), fn () => $mfa->enable($user, $data['code']));
        if ($result['status'] === 'locked') {
            $request->session()->forget('admin_mfa_enrollment_challenge');
            return response()->json(['message' => 'Verification is temporarily locked. Sign in again later.'], 429)->header('Retry-After', (string) ($result['retry_after'] ?? 300));
        }
        $codes = $result['status'] === 'verified' ? $result['value'] : null;
        if (! $codes) {
            $logger->record('security.mfa_enrollment_failed', 'Administrator MFA enrollment verification failed.', $user, $user, status: 'failure', module: 'Security');
            return response()->json(['message' => 'The code is invalid or enrollment has expired. Start sign-in again.'], 422);
        }
        $request->session()->forget('admin_mfa_enrollment_challenge');
        $guard = \Illuminate\Support\Facades\Auth::guard('web');
        $guard->setRememberDuration((int) config('session.remember_lifetime', 43200));
        $guard->login($user, (bool) ($challenge['remember'] ?? false));
        $request->session()->regenerate();
        $request->session()->put(['password_hash_web' => $user->getAuthPassword(), 'auth_version_web' => (int) ($user->auth_version ?? 1)]);
        $user->forceFill(['last_login_at' => now()])->save();
        $logger->record('security.mfa_enabled', 'Administrator completed mandatory MFA enrollment.', $user, $user, module: 'Security');
        return response()->json(['user' => \App\Http\Resources\Api\V1\Shared\Users\UserResource::make($user)->resolve(), 'redirect_to' => '/admin/dashboard', 'recovery_codes' => $codes])->header('Cache-Control', 'no-store, private');
    }

    private function enrollmentUser(mixed $challenge): User
    {
        abort_unless(is_array($challenge) && (int) ($challenge['expires_at'] ?? 0) > now()->timestamp && filled($challenge['challenge_id'] ?? null), 401, 'The administrator enrollment challenge has expired. Sign in again.');
        $user = User::query()->findOrFail($challenge['user_id'] ?? 0);
        abort_unless((int) ($challenge['auth_version'] ?? 0) === (int) ($user->auth_version ?? 1)
            && $user->role === 'admin' && $user->status === 'active' && $user->email_verified_at && ! $user->mfa_enabled_at,
            401, 'The administrator enrollment challenge is no longer valid.');
        return $user;
    }
}
