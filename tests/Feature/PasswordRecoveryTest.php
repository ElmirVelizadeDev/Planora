<?php

namespace Tests\Feature;

use App\Mail\PasswordRecoveryOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    private function headers(): array
    {
        return ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
    }

    private function requestCode(string $email): void
    {
        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/forgot', ['email' => $email])
            ->assertAccepted()
            ->assertJsonPath('message', 'If an account exists for this email, a recovery code has been sent.');
    }

    private function sentCode(string $email): string
    {
        $code = null;
        Mail::assertSent(PasswordRecoveryOtpMail::class, function (PasswordRecoveryOtpMail $mail) use ($email, &$code) {
            if (! $mail->hasTo($email)) return false;
            $code = $mail->code;
            return true;
        });
        return $code;
    }

    public function test_existing_and_unknown_email_requests_are_indistinguishable_and_unknown_creates_no_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'recovery-'.Str::uuid().'@example.test']);
        $this->requestCode($user->email);
        $otp = PasswordResetOtp::where('user_id', $user->id)->firstOrFail();
        $code = $this->sentCode($user->email);
        $this->assertSame(6, strlen($code));
        $this->assertTrue(Hash::check($code, $otp->otp_hash));
        $this->assertNotSame($code, $otp->otp_hash);
        $this->assertSame(0, $otp->attempts);
        $this->assertTrue($otp->expires_at->between(now()->addMinutes(4)->subSeconds(2), now()->addMinutes(5)->addSeconds(2)));

        $missing = 'unknown-'.Str::uuid().'@example.test';
        $missingResponse = $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/forgot', ['email' => $missing])
            ->assertAccepted()
            ->assertJsonPath('message', 'If an account exists for this email, a recovery code has been sent.');
        $this->assertSame(202, $missingResponse->getStatusCode());
        $this->assertDatabaseMissing('password_reset_otps', ['email' => $missing]);
        Mail::assertNotSent(PasswordRecoveryOtpMail::class, fn (PasswordRecoveryOtpMail $mail) => $mail->hasTo($missing));
    }

    public function test_recovery_migration_uses_hashed_secrets_and_database_constraints(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('password_reset_otps', 'otp_hash'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('password_reset_otps', 'recovery_token_hash'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('password_reset_otps', 'expires_at'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('password_reset_otps', 'otp_code'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('password_reset_otps', 'recovery_token'));

        $indexes = \Illuminate\Support\Facades\Schema::getIndexes('password_reset_otps');
        $this->assertTrue(collect($indexes)->contains(fn (array $index) => $index['unique'] && $index['columns'] === ['user_id', 'email']));
        $this->assertTrue(collect($indexes)->contains(fn (array $index) => $index['unique'] && $index['columns'] === ['recovery_token_hash']));
        $foreignKeys = \Illuminate\Support\Facades\Schema::getForeignKeys('password_reset_otps');
        $userForeignKey = collect($foreignKeys)->first(fn (array $foreignKey) => $foreignKey['columns'] === ['user_id']);
        $this->assertNotNull($userForeignKey);
        $this->assertSame('users', $userForeignKey['foreign_table']);
        $this->assertSame('cascade', $userForeignKey['on_delete']);
    }

    public function test_existing_email_is_trimmed_and_lowercased_before_lookup_and_otp_storage(): void
    {
        Mail::fake();
        $email = 'normalized-'.Str::uuid().'@gmail.com';
        $user = User::factory()->create(['email' => $email]);

        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/forgot', ['email' => '  '.mb_strtoupper($email).'  '])
            ->assertAccepted()
            ->assertJsonPath('message', 'If an account exists for this email, a recovery code has been sent.');

        $this->assertDatabaseHas('password_reset_otps', ['user_id' => $user->id, 'email' => $email]);
        Mail::assertSent(PasswordRecoveryOtpMail::class, fn (PasswordRecoveryOtpMail $mail) => $mail->hasTo($email));
    }

    public function test_local_recovery_uses_hashed_database_otp_and_a_terminal_only_code(): void
    {
        Mail::fake();
        $this->app['env'] = 'local';
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $user = User::factory()->create(['email' => 'local-recovery-'.Str::uuid().'@gmail.com']);

        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
            ->assertAccepted()
            ->assertJsonPath('delivery_mode', 'local')
            ->assertJsonMissingPath('code')
            ->assertJsonMissingPath('otp');

        $otp = PasswordResetOtp::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(0, $otp->attempts);
        $this->assertNotEmpty($otp->otp_hash);
        Mail::assertNothingSent();

        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('auth:local-otp', ['flow' => 'recovery', 'email' => $user->email]));
        preg_match('/\): (\d{6})/', \Illuminate\Support\Facades\Artisan::output(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertTrue(Hash::check($matches[1], $otp->fresh()->otp_hash));

        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', [
            'email' => $user->email,
            'code' => $matches[1],
        ])->assertOk()->assertJsonMissingPath('recovery_token');
    }

    public function test_stale_otp_for_previous_email_cannot_be_used_after_email_change(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'old-'.Str::uuid().'@example.test']);
        $oldEmail = $user->email;
        $this->requestCode($oldEmail);
        $code = $this->sentCode($oldEmail);
        $user->email = 'current-'.Str::uuid().'@example.test';
        $user->save();
        $this->requestCode($user->email);
        $currentCode = $this->sentCode($user->email);

        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $oldEmail, 'code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseHas('password_reset_otps', ['email' => $user->email]);
        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $currentCode])
            ->assertOk();
    }

    public function test_forgot_password_route_remains_rate_limited_for_unknown_addresses(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/forgot', [
                'email' => 'missing-'.Str::uuid().'@gmail.com',
            ])->assertAccepted();
        }

        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/forgot', [
            'email' => 'missing-'.Str::uuid().'@gmail.com',
        ])->assertTooManyRequests();
    }

    public function test_email_delivery_failure_is_generic_and_leaves_no_otp_record(): void
    {
        $user = User::factory()->create(['email' => 'recovery-'.Str::uuid().'@example.test']);
        Mail::shouldReceive('to')->once()->with($user->email)->andThrow(new RuntimeException('SMTP connection failed'));
        Log::shouldReceive('warning')->once()->with('Password recovery email delivery failed.', [
            'exception_type' => RuntimeException::class,
        ]);

        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
            ->assertAccepted()
            ->assertJsonPath('message', 'If an account exists for this email, a recovery code has been sent.');

        $this->assertDatabaseMissing('password_reset_otps', ['user_id' => $user->id]);
    }

    public function test_wrong_attempts_lock_code_and_correct_code_creates_short_lived_recovery_token(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'recovery-'.Str::uuid().'@example.test']);
        $this->requestCode($user->email);
        $code = $this->sentCode($user->email);
        $wrong = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $wrong])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame(1, PasswordResetOtp::where('user_id', $user->id)->value('attempts'));

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $code])
            ->assertOk()->assertJsonPath('expires_in', 600)->assertJsonMissingPath('recovery_token');
        $cookie = $response->getCookie('planora_recovery');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/api/v1/auth/password', $cookie->getPath());
        $otp = PasswordResetOtp::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('', $otp->otp_hash);
        $this->assertSame(hash('sha256', $cookie->getValue()), $otp->recovery_token_hash);
        $this->assertGreaterThan(now()->addMinutes(9)->timestamp, $cookie->getExpiresTime());
    }

    public function test_verified_otp_is_single_use_and_password_cannot_reset_without_recovery_token(): void
    {
        Mail::fake();
        $oldPassword = 'Old-password-123';
        $user = User::factory()->create(['email' => 'recovery-'.Str::uuid().'@example.test', 'password' => Hash::make($oldPassword)]);
        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/reset', [
            'password' => 'New-password-123', 'password_confirmation' => 'New-password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('recovery_token');

        $this->requestCode($user->email);
        $code = $this->sentCode($user->email);
        $verify = $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $code])
            ->assertOk()->assertJsonMissingPath('recovery_token');
        $wireCookie = $verify->getCookie('planora_recovery', false);
        $this->assertNotNull($wireCookie);
        $this->withCredentials()->withUnencryptedCookie('planora_recovery', $wireCookie->getValue())
            ->withHeaders($this->headers())->postJson('/api/v1/auth/password/reset', [
                'password' => 'New-password-123', 'password_confirmation' => 'New-password-123',
            ])->assertOk();

        $this->assertFalse(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertTrue(Hash::check('New-password-123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_otps', ['user_id' => $user->id]);
        $this->withHeaders($this->headers())->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $oldPassword,
        ])->assertUnprocessable();
        $this->withHeaders($this->headers())->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'New-password-123',
        ])->assertOk()->assertJsonPath('user.email', $user->email);
        $this->withCredentials()->withUnencryptedCookie('planora_recovery', $wireCookie->getValue())
            ->withHeaders($this->headers())->postJson('/api/v1/auth/password/reset', [
                'password' => 'Another-password-123', 'password_confirmation' => 'Another-password-123',
            ])->assertUnprocessable()->assertJsonValidationErrors('recovery_token');
    }

    public function test_fifth_incorrect_code_locks_verification_until_a_new_code_is_issued(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'recovery-'.Str::uuid().'@example.test']);
        $this->requestCode($user->email);
        $code = $this->sentCode($user->email);
        $wrong = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $wrong])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
        }
        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $wrong])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $code])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame(5, PasswordResetOtp::where('user_id', $user->id)->value('attempts'));
    }

    public function test_expiry_and_resend_invalidate_previous_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'recovery-'.Str::uuid().'@example.test']);
        $time = Carbon::now('UTC');
        Carbon::setTestNow($time);
        try {
            $this->requestCode($user->email);
            $oldCode = $this->sentCode($user->email);
            Carbon::setTestNow($time->copy()->addMinutes(6));
            $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $oldCode])
                ->assertUnprocessable()->assertJsonValidationErrors('code');

            Carbon::setTestNow($time->copy()->addSeconds(61));
            $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/resend', ['email' => $user->email])->assertOk();
            $codes = Mail::sent(PasswordRecoveryOtpMail::class)
                ->filter(fn (PasswordRecoveryOtpMail $mail) => $mail->hasTo($user->email))
                ->map(fn (PasswordRecoveryOtpMail $mail) => $mail->code)->values()->all();
            $newCode = $codes[1];
            $this->assertNotSame($oldCode, $newCode);
            $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $oldCode])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
            $this->withHeaders($this->headers())->postJson('/api/v1/auth/password/verify', ['email' => $user->email, 'code' => $newCode])->assertOk();
        } finally {
            Carbon::setTestNow();
        }
    }
}
