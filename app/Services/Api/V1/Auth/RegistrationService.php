<?php

namespace App\Services\Api\V1\Auth;

use App\Mail\RegistrationOtpMail;
use App\Models\EmailVerificationOtp;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\Api\V1\Admin\UserActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationService
{
    private const OTP_TTL_MINUTES = 5;
    private const OTP_RESEND_SECONDS = 60;
    private const MAX_OTP_ATTEMPTS = 5;

    public function __construct(private readonly UserActivityLogger $activityLogger)
    {
    }

    public function begin(array $attributes): bool
    {
        $email = mb_strtolower(trim($attributes['email']));
        $code = $this->newCode();
        $otpHash = Hash::make($code);
        $developmentMode = app()->environment('local');

        $issueStatus = DB::transaction(function () use ($attributes, $email, $otpHash, $code) {
            if (User::withTrashed()->where('email', $email)->lockForUpdate()->exists()) {
                return 'registered';
            }

            $otp = EmailVerificationOtp::query()->where('email', $email)->lockForUpdate()->first();
            $pending = PendingRegistration::query()->where('email', $email)->lockForUpdate()->first();

            if ($otp?->last_sent_at && $otp->last_sent_at->addSeconds(self::OTP_RESEND_SECONDS)->isFuture()
                && $pending?->expires_at?->isFuture()) {
                return 'cooldown';
            }

            $pending ??= new PendingRegistration();
            $pending->name = $attributes['name'];
            $pending->email = $email;
            $pending->password_hash = Hash::make($attributes['password']);
            $pending->expires_at = now()->addDay();
            $pending->save();

            $otp ??= new EmailVerificationOtp();
            $otp->email = $email;
            $otp->otp_hash = $otpHash;
            $otp->expires_at = now()->addMinutes(self::OTP_TTL_MINUTES);
            $otp->last_sent_at = now();
            $otp->attempts = 0;
            $otp->save();

            return 'issued';
        });

        if (! $developmentMode && $issueStatus === 'issued') {
            $this->sendCode($email, $code, $otpHash);
        }

        return $developmentMode;
    }

    public function resend(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        $code = $this->newCode();
        $otpHash = Hash::make($code);
        $developmentMode = app()->environment('local');

        $shouldSend = DB::transaction(function () use ($email, $otpHash, $code) {
            $otp = EmailVerificationOtp::query()->where('email', $email)->lockForUpdate()->first();
            $pending = PendingRegistration::query()->where('email', $email)->lockForUpdate()->first();
            if (! $pending || ! $pending->password_hash || ! $pending->expires_at?->isFuture() || ! $otp) {
                return false;
            }
            if (! $otp->last_sent_at || $otp->last_sent_at->addSeconds(self::OTP_RESEND_SECONDS)->isFuture()) {
                return false;
            }

            $otp->otp_hash = $otpHash;
            $otp->expires_at = now()->addMinutes(self::OTP_TTL_MINUTES);
            $otp->last_sent_at = now();
            $otp->attempts = 0;
            $otp->save();

            return true;
        });

        if (! $developmentMode && $shouldSend) {
            $this->sendCode($email, $code, $otpHash);
        }

        return $developmentMode;
    }

    public function verify(string $email, string $code): User
    {
        $result = DB::transaction(function () use ($email, $code) {
            $email = mb_strtolower(trim($email));
            $otp = EmailVerificationOtp::query()->where('email', $email)->lockForUpdate()->first();
            $pending = PendingRegistration::query()->where('email', $email)->lockForUpdate()->first();

            if (! $pending || ! $otp || ! $pending->password_hash || ! $otp->otp_hash) {
                return ['error' => 'invalid'];
            }
            if (! $pending->expires_at || $pending->expires_at->isPast()) {
                return ['error' => 'expired'];
            }
            if (! $otp->expires_at || $otp->expires_at->isPast()) {
                return ['error' => 'expired'];
            }
            if ($otp->attempts >= self::MAX_OTP_ATTEMPTS) {
                return ['error' => 'locked'];
            }
            if (! Hash::check($code, $otp->otp_hash)) {
                $otp->attempts++;
                $otp->save();

                return ['error' => $otp->attempts >= self::MAX_OTP_ATTEMPTS ? 'locked' : 'invalid'];
            }

            if (User::withTrashed()->where('email', $pending->email)->lockForUpdate()->exists()) {
                return ['error' => 'email_taken'];
            }

            $user = new User();
            $user->name = $pending->name;
            $user->email = $pending->email;
            $user->password = $pending->password_hash;
            $user->role = 'user';
            $user->status = 'active';
            $user->email_verified_at = now();
            $user->save();

            $otp->delete();
            $pending->delete();

            $this->activityLogger->record('user.registered', 'User account registered and email verified.', $user, $user);

            return ['user' => $user];
        });

        if (isset($result['error'])) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is incorrect, expired, or no longer valid. Request a new code if needed.'],
            ]);
        }

        return $result['user'];
    }

    private function sendCode(string $email, string $code, string $otpHash): void
    {
        try {
            Mail::to($email)->send(new RegistrationOtpMail($code));
        } catch (Throwable $exception) {
            EmailVerificationOtp::query()->where('email', $email)->where('otp_hash', $otpHash)->update([
                'expires_at' => now(),
                'last_sent_at' => now()->subSeconds(self::OTP_RESEND_SECONDS),
                'updated_at' => now(),
            ]);

            Log::warning('Registration verification email delivery failed.', [
                'exception_type' => get_class($exception),
            ]);
        }
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
