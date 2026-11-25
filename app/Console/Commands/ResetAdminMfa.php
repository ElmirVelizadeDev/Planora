<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ResetAdminMfa extends Command
{
    protected $signature = 'auth:mfa-reset {email : Exact email address of the administrator to recover}';

    protected $description = 'Reset one administrator MFA enrollment for local recovery';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Administrator MFA recovery is available only in the local environment.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Administrator MFA recovery requires an interactive terminal.');

            return self::FAILURE;
        }

        $email = trim((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $this->error('Enter a valid administrator email address. No account was changed.');

            return self::FAILURE;
        }

        $admin = User::query()->where('email', $email)->first();
        if (! $admin || $admin->role !== 'admin') {
            $this->error('No eligible administrator account was found. No account was changed.');

            return self::FAILURE;
        }

        if (! $this->confirm('Reset MFA for this administrator and invalidate their existing sessions?', false)) {
            $this->warn('MFA recovery cancelled. No account was changed.');

            return self::FAILURE;
        }

        $confirmationEmail = trim((string) $this->ask('Re-enter the administrator email address to confirm'));
        if (! hash_equals($email, $confirmationEmail)) {
            $this->error('The email confirmation did not match. No account was changed.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($admin, $email): void {
                $locked = User::query()->whereKey($admin->id)->lockForUpdate()->first();
                if (! $locked || $locked->email !== $email || $locked->role !== 'admin') {
                    throw new \RuntimeException('The selected account is no longer eligible for MFA recovery.');
                }

                $locked->forceFill([
                    'mfa_secret' => null,
                    'mfa_enabled_at' => null,
                    'mfa_enrollment_expires_at' => null,
                    'mfa_last_counter' => null,
                    'auth_version' => (int) ($locked->auth_version ?? 1) + 1,
                ]);
                $locked->setRememberToken(Str::random(60));
                $locked->save();
                $locked->tokens()->delete();

                DB::table('admin_mfa_recovery_codes')->where('user_id', $locked->id)->delete();
                DB::table('admin_mfa_attempts')->where('user_id', $locked->id)->delete();
            }, 3);
        } catch (Throwable $exception) {
            Log::warning('Local administrator MFA recovery failed.', [
                'user_id' => $admin->id,
                'exception_type' => get_class($exception),
            ]);
            $this->error('MFA recovery did not complete. No MFA secrets were displayed. Check local logs and database availability.');

            return self::FAILURE;
        }

        Log::notice('Administrator MFA enrollment reset through the local recovery command.', [
            'user_id' => $admin->id,
        ]);
        $this->info('MFA enrollment reset for the selected administrator. Existing sessions were invalidated. MFA remains mandatory. Sign in with the account password to enroll a new authenticator.');

        return self::SUCCESS;
    }
}
