<?php

namespace App\Console\Commands;

use App\Models\EmailVerificationOtp;
use App\Models\PasswordResetOtp;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class IssueLocalOtp extends Command
{
    protected $signature = 'auth:local-otp {flow : registration or recovery} {email : The account email address}';

    protected $description = 'Generate a fresh, short-lived OTP for local development only.';

    public function handle(): int
    {
        $localOrIsolatedTest = app()->environment('local');
        if (app()->environment('testing')) {
            $database = config('database.connections.'.config('database.default').'.database');
            $localOrIsolatedTest = config('database.default') === 'mysql'
                && is_string($database)
                && preg_match('/(^|[_-])test(?:ing)?($|[_-])/i', $database) === 1;
        }

        if (! $localOrIsolatedTest) {
            $this->error('This command is available only in local development or an isolated MySQL test database.');

            return self::FAILURE;
        }

        $flow = $this->argument('flow');
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (! in_array($flow, ['registration', 'recovery'], true) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Use a supported flow and a valid email address.');

            return self::FAILURE;
        }

        $code = DB::transaction(function () use ($flow, $email) {
            if ($flow === 'registration') {
                $pending = PendingRegistration::query()->where('email', $email)->lockForUpdate()->first();
                $otp = EmailVerificationOtp::query()->where('email', $email)->lockForUpdate()->first();
                if (! $pending?->password_hash || ! $pending->expires_at?->isFuture() || ! $otp) {
                    return null;
                }
            } else {
                $user = User::query()->where('email', $email)->lockForUpdate()->first();
                $otp = $user ? PasswordResetOtp::query()
                    ->where('user_id', $user->id)
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first() : null;
                if (! $otp || $otp->verified_at) {
                    return null;
                }
            }

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otp->otp_hash = Hash::make($code);
            $otp->expires_at = now()->addMinutes(5);
            $otp->attempts = 0;
            $otp->last_sent_at = now();
            if ($flow === 'recovery') {
                $otp->verified_at = null;
                $otp->recovery_token_hash = null;
                $otp->recovery_expires_at = null;
            }
            $otp->save();

            return $code;
        });

        if ($code === null) {
            $this->error('No eligible local verification or recovery request was found.');

            return self::FAILURE;
        }

        $this->line('Local one-time code (expires in 5 minutes): '.$code);
        $this->warn('This code was written only to this terminal and its hash was stored in the database. Do not copy it into logs or shared output.');

        return self::SUCCESS;
    }
}
