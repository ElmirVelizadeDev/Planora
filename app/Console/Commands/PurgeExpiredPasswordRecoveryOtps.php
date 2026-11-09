<?php

namespace App\Console\Commands;

use App\Models\PasswordResetOtp;
use Illuminate\Console\Command;

class PurgeExpiredPasswordRecoveryOtps extends Command
{
    protected $signature = 'auth:purge-expired-password-recovery-otps';

    protected $description = 'Remove expired password recovery OTP and authorization records.';

    public function handle(): int
    {
        $deleted = PasswordResetOtp::query()
            ->where(fn ($query) => $query
                ->where(fn ($otp) => $otp->whereNull('verified_at')->where('expires_at', '<=', now()))
                ->orWhere(fn ($otp) => $otp->whereNotNull('verified_at')->where('recovery_expires_at', '<=', now())))
            ->delete();

        $this->info("Removed {$deleted} expired password recovery record(s).");

        return self::SUCCESS;
    }
}
