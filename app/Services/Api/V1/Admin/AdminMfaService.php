<?php

namespace App\Services\Api\V1\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminMfaService
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function newSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function beginEnrollment(User $user): array
    {
        $secret = $this->newSecret();
        $expiresAt = now()->addMinutes(max(1, (int) config('auth.admin_mfa.enrollment_minutes', 10)));
        DB::transaction(function () use ($user, $secret, $expiresAt): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->mfa_enabled_at) abort(409, 'MFA is already enabled.');
            $locked->forceFill([
                'mfa_secret' => Crypt::encryptString($secret),
                'mfa_enabled_at' => null,
                'mfa_enrollment_expires_at' => $expiresAt,
                'mfa_last_counter' => null,
            ])->save();
        });

        return [
            'secret' => $secret,
            'otpauth_uri' => 'otpauth://totp/'.rawurlencode('Planora:'.$user->email).'?'.http_build_query([
                'secret' => $secret, 'issuer' => 'Planora', 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30,
            ], '', '&', PHP_QUERY_RFC3986),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function enable(User $user, string $code): ?array
    {
        return DB::transaction(function () use ($user, $code): ?array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->mfa_enabled_at || ! $locked->mfa_secret || ! $locked->mfa_enrollment_expires_at?->isFuture()) return null;
            $secret = Crypt::decryptString($locked->mfa_secret);
            $counter = $this->matchingCounter($secret, $code);
            if ($counter === null) return null;

            $codes = array_map(fn () => strtoupper(bin2hex(random_bytes(5))), range(1, 10));
            DB::table('admin_mfa_recovery_codes')->where('user_id', $locked->id)->delete();
            foreach ($codes as $recoveryCode) {
                DB::table('admin_mfa_recovery_codes')->insert([
                    'user_id' => $locked->id, 'code_hash' => Hash::make($recoveryCode),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $locked->forceFill([
                'mfa_enabled_at' => now(), 'mfa_enrollment_expires_at' => null,
                'mfa_last_counter' => $counter,
            ])->save();
            return $codes;
        });
    }

    public function verifyTotp(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->lockForUpdate()->find($user->id);
            if (! $locked?->mfa_enabled_at || ! $locked->mfa_secret) return false;
            $counter = $this->matchingCounter(Crypt::decryptString($locked->mfa_secret), $code);
            if ($counter === null || ($locked->mfa_last_counter !== null && $counter <= (int) $locked->mfa_last_counter)) return false;
            $locked->forceFill(['mfa_last_counter' => $counter])->save();
            return true;
        });
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $rows = DB::table('admin_mfa_recovery_codes')->where('user_id', $user->id)->whereNull('used_at')->lockForUpdate()->get();
            foreach ($rows as $row) {
                if (Hash::check(strtoupper(trim($code)), $row->code_hash)) {
                    DB::table('admin_mfa_recovery_codes')->where('id', $row->id)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);
                    return true;
                }
            }
            return false;
        });
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $codes = array_map(fn () => strtoupper(bin2hex(random_bytes(5))), range(1, 10));
            DB::table('admin_mfa_recovery_codes')->where('user_id', $user->id)->delete();
            foreach ($codes as $code) {
                DB::table('admin_mfa_recovery_codes')->insert(['user_id' => $user->id, 'code_hash' => Hash::make($code), 'created_at' => now(), 'updated_at' => now()]);
            }
            return $codes;
        });
    }

    public function disable(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            DB::table('admin_mfa_recovery_codes')->where('user_id', $user->id)->delete();
            $locked->forceFill(['mfa_secret' => null, 'mfa_enabled_at' => null, 'mfa_enrollment_expires_at' => null, 'mfa_last_counter' => null])->save();
        });
    }

    public function verifyPassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->getAuthPassword());
    }

    public function isEnabled(User $user): bool
    {
        return $user->role === 'admin' && $user->mfa_enabled_at !== null;
    }

    private function matchingCounter(string $secret, string $code): ?int
    {
        if (! preg_match('/^\d{6}$/', $code)) return null;
        $current = intdiv(now()->timestamp, 30);
        foreach ([$current - 1, $current, $current + 1] as $counter) {
            if ($counter < 0) continue;
            $binary = $this->base32Decode($secret);
            $digest = hash_hmac('sha1', pack('N*', 0).pack('N*', $counter), $binary, true);
            $offset = ord($digest[19]) & 0x0f;
            $number = ((ord($digest[$offset]) & 0x7f) << 24) | ((ord($digest[$offset + 1]) & 0xff) << 16)
                | ((ord($digest[$offset + 2]) & 0xff) << 8) | (ord($digest[$offset + 3]) & 0xff);
            if (hash_equals(str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT), $code)) return $counter;
        }
        return null;
    }

    private function base32Encode(string $input): string
    {
        $bits = '';
        foreach (str_split($input) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) $encoded .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        return $encoded;
    }

    private function base32Decode(string $input): string
    {
        $bits = '';
        foreach (str_split(rtrim(strtoupper($input), '=')) as $char) {
            $index = strpos(self::BASE32, $char);
            if ($index === false) throw new \RuntimeException('MFA secret is invalid.');
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $binary .= chr(bindec($byte));
        return $binary;
    }
}
