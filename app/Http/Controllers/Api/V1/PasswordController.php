<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\VerifyPasswordRecoveryOtpRequest;
use App\Services\Api\V1\Auth\PasswordRecoveryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordController extends Controller
{
    private const RECOVERY_COOKIE = 'planora_recovery';
    private const RECOVERY_COOKIE_PATH = '/api/v1/auth/password';

    public function requestCode(ForgotPasswordRequest $request, PasswordRecoveryService $recovery): JsonResponse
    {
        $recovery->issue($request->validated('email'));

        $payload = [
            'message' => 'If an account exists for this email, a recovery code has been sent.',
            'expires_in' => 300,
            'resend_in' => 60,
            'delivery_mode' => app()->environment('local') ? 'local' : 'email',
        ];

        return response()->json($payload, 202)
            ->withCookie(Cookie::forget(self::RECOVERY_COOKIE, self::RECOVERY_COOKIE_PATH, config('session.domain')));
    }

    public function resendCode(ForgotPasswordRequest $request, PasswordRecoveryService $recovery): JsonResponse
    {
        $recovery->issue($request->validated('email'));

        $payload = [
            'message' => 'If an account exists for this email, a new recovery code has been sent.',
            'expires_in' => 300,
            'resend_in' => 60,
            'delivery_mode' => app()->environment('local') ? 'local' : 'email',
        ];

        return response()->json($payload)
            ->withCookie(Cookie::forget(self::RECOVERY_COOKIE, self::RECOVERY_COOKIE_PATH, config('session.domain')));
    }

    public function verifyCode(VerifyPasswordRecoveryOtpRequest $request, PasswordRecoveryService $recovery): JsonResponse
    {
        $verified = $recovery->verify($request->validated('email'), $request->validated('code'));
        $cookie = Cookie::make(
            self::RECOVERY_COOKIE,
            $verified['recovery_token'],
            (int) ceil($verified['expires_in'] / 60),
            self::RECOVERY_COOKIE_PATH,
            config('session.domain'),
            (bool) config('session.secure'),
            true,
            false,
            'lax',
        );

        return response()->json(['expires_in' => $verified['expires_in']])->withCookie($cookie);
    }

    public function reset(ResetPasswordRequest $request, PasswordRecoveryService $recovery): JsonResponse
    {
        $recovery->reset((string) $request->cookie(self::RECOVERY_COOKIE, ''), $request->validated('password'));

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Your password has been reset. You can now sign in.'])
            ->withCookie(Cookie::forget(self::RECOVERY_COOKIE, self::RECOVERY_COOKIE_PATH, config('session.domain')));
    }
}
