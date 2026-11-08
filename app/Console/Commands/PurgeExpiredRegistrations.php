<?php

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeExpiredRegistrations extends Command
{
    protected $signature = 'auth:purge-expired-registrations';

    protected $description = 'Remove expired unverified registration attempts.';

    public function handle(): int
    {
        [$deletedOtps, $deletedRegistrations] = DB::transaction(function () {
            $expiredPendingEmails = PendingRegistration::query()
                ->where('expires_at', '<=', now())
                ->select('email');

            $deletedOtps = DB::table('email_verification_otps')
                ->whereIn('email', $expiredPendingEmails)
                ->delete();

            $deletedRegistrations = PendingRegistration::query()
                ->where('expires_at', '<=', now())
                ->delete();

            // Also remove any orphaned OTP records left by an interrupted/manual operation.
            $deletedOtps += DB::table('email_verification_otps')
                ->where('created_at', '<=', now()->subDay())
                ->whereNotIn('email', PendingRegistration::query()->select('email'))
                ->delete();

            return [$deletedOtps, $deletedRegistrations];
        });

        $this->info("Removed {$deletedOtps} OTP record(s) and {$deletedRegistrations} pending registration(s).");

        return self::SUCCESS;
    }
}
