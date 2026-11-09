<?php

namespace App\Services\Api\V1\Auth;

use App\Mail\PasswordRecoveryOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordRecoveryService
{
    private const OTP_TTL_MINUTES = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;
    private const RECOVERY_TTL_MINUTES = 10;

    public function issue(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $issued = DB::transaction(function () use ($email) {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if (! $user) {
                return ['missing' => true];
            }

            $otp = PasswordResetOtp::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($otp && $otp->email === $email && $otp->last_sent_at
                && $otp->last_sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isFuture()) {
                return ['cooldown' => true];
            }

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpHash = Hash::make($code);
            $otp ??= new PasswordResetOtp();
            $otp->fill([
                'user_id' => $user->id,
                'email' => $email,
                'otp_hash' => $otpHash,
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'attempts' => 0,
                'verified_at' => null,
                'recovery_token_hash' => null,
                'recovery_expires_at' => null,
                'last_sent_at' => now(),
            ])->save();

            return ['user' => $user, 'code' => $code, 'otp_hash' => $otpHash];
        });
        if (isset($issued['missing'])) {
            return;
        }
        if (isset($issued['cooldown'])) return;

        $user = $issued['user'];
        $code = $issued['code'];
        $otpHash = $issued['otp_hash'];

        // Local development retrieves codes through a protected Artisan command.
        if (app()->environment('local')) {
            return;
        }

        try {
            Mail::to($email)->send(new PasswordRecoveryOtpMail($code));
        } catch (Throwable $exception) {
            PasswordResetOtp::query()->where('user_id', $user->id)->where('otp_hash', $otpHash)->delete();
            Log::warning('Password recovery email delivery failed.', [
                'exception_type' => get_class($exception),
            ]);

        }
    }

    public function verify(string $email, string $code): array
    {
        $email = mb_strtolower(trim($email));
        $result = DB::transaction(function () use ($email, $code) {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if (! $user) {
                PasswordResetOtp::query()->where('email', $email)->delete();

                return ['error' => 'invalid'];
            }

            $otp = PasswordResetOtp::query()
                ->where('user_id', $user->id)
                ->where('email', $email)
                ->lockForUpdate()
                ->first();
            if (! $otp || $otp->verified_at || ! $otp->otp_hash) {
                return ['error' => 'invalid'];
            }
            if (! $otp->expires_at || $otp->expires_at->isPast()) {
                return ['error' => 'expired'];
            }
            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                return ['error' => 'locked'];
            }
            if (! Hash::check($code, $otp->otp_hash)) {
                $otp->attempts++;
                $otp->save();
                return ['error' => $otp->attempts >= self::MAX_ATTEMPTS ? 'locked' : 'invalid'];
            }

            $token = Str::random(64);
            $otp->verified_at = now();
            $otp->otp_hash = '';
            $otp->recovery_token_hash = hash('sha256', $token);
            $otp->recovery_expires_at = now()->addMinutes(self::RECOVERY_TTL_MINUTES);
            $otp->save();

            return ['token' => $token];
        });

        if (isset($result['error'])) {
            throw ValidationException::withMessages([
                'code' => ['The code is incorrect, expired, or no longer valid. Request a new code if needed.'],
            ]);
        }

        return ['recovery_token' => $result['token'], 'expires_in' => self::RECOVERY_TTL_MINUTES * 60];
    }

    public function reset(string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password) {
            $otp = PasswordResetOtp::query()
                ->where('recovery_token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $otp || ! $otp->verified_at || ! $otp->recovery_expires_at?->isFuture()) {
                throw ValidationException::withMessages(['recovery_token' => ['This recovery session has expired. Request a new code.']]);
            }

            $user = User::query()->whereKey($otp->user_id)->lockForUpdate()->first();
            if (! $user || $user->email !== $otp->email) {
                $otp->delete();
                throw ValidationException::withMessages(['recovery_token' => ['This recovery session is no longer valid.']]);
            }

            $user->password = $password;
            $user->setRememberToken(Str::random(60));
            $user->save();
            $user->tokens()->delete();
            $otp->delete();
        });
    }
}
