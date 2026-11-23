<?php

namespace App\Jobs;

use App\Mail\PasswordRecoveryOtpMail;
use App\Mail\RegistrationOtpMail;
use App\Models\EmailVerificationOtp;
use App\Models\PasswordResetOtp;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverAuthenticationOtp implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60];
    public int $uniqueFor = 900;

    public function __construct(
        public readonly string $flow,
        public readonly string $email,
        public readonly string $encryptedCode,
        public readonly string $otpHash,
        public readonly ?int $userId = null,
        public readonly int $cooldownSeconds = 60,
    ) {
    }

    public function uniqueId(): string
    {
        return $this->flow.':'.$this->email.':'.$this->otpHash;
    }

    public function handle(): void
    {
        $code = Crypt::decryptString($this->encryptedCode);
        if (! $this->stillCurrent($code)) return;
        $mail = $this->flow === 'registration' ? new RegistrationOtpMail($code) : new PasswordRecoveryOtpMail($code);
        Mail::to($this->email)->send($mail);
    }

    public function failed(Throwable $exception): void
    {
        if ($this->flow === 'registration') {
            EmailVerificationOtp::query()->where('email', $this->email)->where('otp_hash', $this->otpHash)->update([
                'expires_at' => now(), 'last_sent_at' => now()->subSeconds($this->cooldownSeconds), 'updated_at' => now(),
            ]);
        } else {
            PasswordResetOtp::query()->where('user_id', $this->userId)->where('otp_hash', $this->otpHash)->delete();
        }
        Log::warning('Authentication OTP delivery failed after retries.', ['flow' => $this->flow, 'exception_type' => get_class($exception)]);
    }

    private function stillCurrent(string $code): bool
    {
        if ($this->flow === 'registration') {
            $otp = EmailVerificationOtp::query()->where('email', $this->email)->first();
            $pending = PendingRegistration::query()->where('email', $this->email)->first();
            return $otp && $otp->otp_hash === $this->otpHash && $otp->expires_at?->isFuture()
                && $pending && $pending->expires_at?->isFuture() && Hash::check($code, $otp->otp_hash);
        }

        $otp = PasswordResetOtp::query()->where('user_id', $this->userId)->where('email', $this->email)->first();
        return $otp && $otp->otp_hash === $this->otpHash && ! $otp->verified_at
            && $otp->expires_at?->isFuture() && User::query()->whereKey($this->userId)->where('email', $this->email)->exists()
            && Hash::check($code, $otp->otp_hash);
    }
}
