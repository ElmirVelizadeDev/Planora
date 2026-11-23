<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeAdminMfaAttempts extends Command
{
    protected $signature = 'auth:purge-admin-mfa-attempts';
    protected $description = 'Remove expired administrator MFA challenge counters';

    public function handle(): int
    {
        DB::table('admin_mfa_attempts')
            ->where('challenge_key', '<>', 'account')
            ->where('updated_at', '<', now()->subDay())
            ->delete();
        DB::table('admin_mfa_attempts')
            ->where('challenge_key', 'account')
            ->whereNull('locked_until')
            ->where('updated_at', '<', now()->subDays(90))
            ->delete();

        return self::SUCCESS;
    }
}
