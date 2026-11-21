<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\VerifyAdminStepUpRequest;
use App\Models\User;
use App\Services\Api\V1\Admin\AdminMfaService;
use App\Services\Api\V1\Admin\AdminMfaQrCodeService;
use App\Services\Api\V1\Admin\UserActivityLogger;
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

        return response()->json($setup)->header('Cache-Control', 'no-store, private');
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
        $user = $request->user();
        $mfa->disable($user);
        $request->session()->forget('admin_step_up_grant');
        $logger->record('security.mfa_disabled', 'Administrator disabled MFA after step-up verification.', $user, $user, module: 'Security');
        return response()->json(['enabled' => false]);
    }

    public function regenerateRecoveryCodes(Request $request, AdminMfaService $mfa, UserActivityLogger $logger): JsonResponse
    {
        $user = $request->user();
        if (! $user->mfa_enabled_at) return response()->json(['message' => 'MFA is not enabled.'], 409);
        $codes = $mfa->regenerateRecoveryCodes($user);
        $logger->record('security.mfa_recovery_regenerated', 'Administrator regenerated MFA recovery codes.', $user, $user, module: 'Security');
        return response()->json(['recovery_codes' => $codes])->header('Cache-Control', 'no-store, private');
    }

    public function verifyStepUp(VerifyAdminStepUpRequest $request, AdminMfaService $mfa, UserActivityLogger $logger): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $usedRecoveryCode = $mfa->isEnabled($user) && ! isset($data['code']) && isset($data['recovery_code']);
        $valid = $mfa->isEnabled($user)
            ? (isset($data['code']) ? $mfa->verifyTotp($user, $data['code']) : ($usedRecoveryCode ? $mfa->consumeRecoveryCode($user, $data['recovery_code']) : false))
            : (($data['password'] ?? null) ? Hash::check($data['password'], $user->getAuthPassword()) : false);

        if (! $valid) {
            $logger->record('security.step_up_failed', 'Administrator step-up verification failed.', $user, $user, ['purpose' => $data['purpose']], 'Security', 'failure');
            throw ValidationException::withMessages(['code' => ['Identity verification failed.']]);
        }

        $request->session()->put('admin_step_up_grant', [
            'purpose' => $data['purpose'], 'expires_at' => now()->addMinute()->timestamp,
            'user_id' => $user->id, 'session_hash' => hash('sha256', $request->session()->getId()),
        ]);
        $logger->record('security.step_up_succeeded', 'An authorized operator completed step-up verification.', $user, $user, ['purpose' => $data['purpose']], 'Security');
        if ($usedRecoveryCode) $logger->record('security.mfa_recovery_used', 'Administrator used a single-use MFA recovery code for step-up.', $user, $user, module: 'Security');
        return response()->json(['verified' => true, 'expires_in' => 60])->header('Cache-Control', 'no-store, private');
    }
}
