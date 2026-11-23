<?php

namespace App\Services\Api\V1\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminMfaAttemptService
{
    /** Serialize per-account verification and keep counters in shared SQL storage. */
    public function verify(User $user, string $flow, string $challengeId, callable $verification): array
    {
        return DB::transaction(function () use ($user, $flow, $challengeId, $verification): array {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $now = now();
            $challengeKey = hash('sha256', $challengeId);
            $account = $this->lockedRow($user->id, 'account', 'account');
            $challenge = $this->lockedRow($user->id, $flow, $challengeKey);
            foreach ([$account, $challenge] as $row) {
                if ($row->locked_until && \Illuminate\Support\Carbon::parse($row->locked_until)->isFuture()) {
                    return ['status' => 'locked', 'retry_after' => max(1, now()->diffInSeconds(\Illuminate\Support\Carbon::parse($row->locked_until), false))];
                }
            }

            $value = $verification();
            if ($value !== false && $value !== null) {
                DB::table('admin_mfa_attempts')->where('user_id', $user->id)->where('flow', $flow)->where('challenge_key', $challengeKey)->delete();
                DB::table('admin_mfa_attempts')->where('user_id', $user->id)->where('flow', 'account')->where('challenge_key', 'account')->delete();
                return ['status' => 'verified', 'value' => $value];
            }

            $max = max(1, (int) config('auth.admin_mfa.challenge_max_attempts', 5));
            $window = max(1, (int) config('auth.admin_mfa.challenge_window_minutes', 5));
            $lockMinutes = max(1, (int) config('auth.admin_mfa.challenge_lockout_minutes', 5));
            $accountMax = max($max, (int) config('auth.admin_mfa.account_max_attempts', 15));
            $accountWindow = max(1, (int) config('auth.admin_mfa.account_window_minutes', 15));
            $accountLock = max(1, (int) config('auth.admin_mfa.account_lockout_minutes', 15));
            $this->increment($challenge, $max, $window, $lockMinutes, $now);
            $this->increment($account, $accountMax, $accountWindow, $accountLock, $now);
            $fresh = DB::table('admin_mfa_attempts')->where('id', $challenge->id)->first();
            $freshAccount = DB::table('admin_mfa_attempts')->where('id', $account->id)->first();
            $retryAfter = max(0,
                $fresh->locked_until ? now()->diffInSeconds(\Illuminate\Support\Carbon::parse($fresh->locked_until), false) : 0,
                $freshAccount->locked_until ? now()->diffInSeconds(\Illuminate\Support\Carbon::parse($freshAccount->locked_until), false) : 0
            );
            return $retryAfter > 0 ? ['status' => 'locked', 'retry_after' => $retryAfter] : ['status' => 'invalid'];
        }, 3);
    }

    private function lockedRow(int $userId, string $flow, string $key): object
    {
        DB::table('admin_mfa_attempts')->insertOrIgnore(['user_id' => $userId, 'flow' => $flow, 'challenge_key' => $key, 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('admin_mfa_attempts')->where('user_id', $userId)->where('flow', $flow)->where('challenge_key', $key)->lockForUpdate()->first();
    }

    private function increment(object $row, int $max, int $window, int $lockMinutes, $now): void
    {
        $windowExpires = $row->window_expires_at ? \Illuminate\Support\Carbon::parse($row->window_expires_at) : null;
        $lockedUntil = $row->locked_until ? \Illuminate\Support\Carbon::parse($row->locked_until) : null;
        $attempts = $windowExpires && $now->lt($windowExpires) ? (int) $row->attempts + 1 : 1;
        DB::table('admin_mfa_attempts')->where('id', $row->id)->update([
            'attempts' => $attempts,
            'window_expires_at' => $attempts === 1 ? $now->copy()->addMinutes($window) : $row->window_expires_at,
            'locked_until' => $attempts >= $max ? $now->copy()->addMinutes($lockMinutes) : ($lockedUntil && $lockedUntil->isFuture() ? $lockedUntil : null),
            'updated_at' => $now,
        ]);
    }
}
