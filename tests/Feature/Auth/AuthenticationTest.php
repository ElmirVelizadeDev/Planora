<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\EmailVerificationOtp;
use App\Models\PendingRegistration;
use App\Mail\RegistrationOtpMail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private function statefulHeaders(): array
    {
        return [
            'Origin' => 'http://planora.test',
            'Referer' => 'http://planora.test/',
        ];
    }

    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Authentication Test User',
            'email' => 'auth-'.Str::uuid().'@gmail.com',
            'password' => 'Test-password-123',
            'password_confirmation' => 'Test-password-123',
            'terms_accepted' => true,
        ], $overrides);
    }

    public function test_registration_requires_terms_acceptance_before_creating_pending_registration_or_otp(): void
    {
        Mail::fake();
        foreach ([[], ['terms_accepted' => false]] as $acceptance) {
            $attributes = $this->attributes();
            if (! array_key_exists('terms_accepted', $acceptance)) unset($attributes['terms_accepted']);
            $attributes = array_merge($attributes, $acceptance);
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register', $attributes)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('terms_accepted');

            $this->assertDatabaseMissing('pending_registrations', ['email' => $attributes['email']]);
            $this->assertDatabaseMissing('email_verification_otps', ['email' => $attributes['email']]);
        }
        Mail::assertNothingSent();
    }

    private function sentOtp(string $email): string
    {
        $code = '';
        Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail) use ($email, &$code) {
            if (! $mail->hasTo($email)) return false;
            $code = $mail->code;
            return true;
        });

        return $code;
    }

    public function test_registration_sends_hashed_otp_and_creates_no_account_before_verification(): void
    {
        Mail::fake();
        $attributes = $this->attributes();

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $attributes)
            ->assertAccepted()
            ->assertJsonPath('email', $attributes['email'])
            ->assertJsonPath('expires_in', 300)
            ->assertJsonPath('resend_in', 60);

        $this->assertDatabaseMissing('users', ['email' => $attributes['email']]);
        $pending = PendingRegistration::where('email', $attributes['email'])->firstOrFail();
        $this->assertTrue(Hash::check($attributes['password'], $pending->password_hash));
        $code = $this->sentOtp($attributes['email']);
        $this->assertSame(6, strlen($code));
        $otp = EmailVerificationOtp::where('email', $attributes['email'])->firstOrFail();
        $this->assertTrue(Hash::check($code, $otp->otp_hash));
        $this->assertNotSame($code, $otp->otp_hash);
        $this->assertNull($otp->otp_code);
        $this->assertNotNull($otp->expires_at);
        $this->assertTrue($pending->expires_at->greaterThan(now()->addHours(23)));
        $this->assertGuest('web');
    }

    public function test_otp_storage_is_separate_from_pending_registration_and_users_tables(): void
    {
        $this->assertTrue(Schema::hasTable('email_verification_otps'));
        $this->assertTrue(Schema::hasColumn('email_verification_otps', 'otp_hash'));
        $this->assertFalse(Schema::hasColumn('email_verification_otps', 'otp_code'));
        $this->assertTrue(Schema::hasColumn('email_verification_otps', 'expires_at'));
        $this->assertFalse(Schema::hasColumn('pending_registrations', 'otp_hash'));
        $this->assertFalse(Schema::hasColumn('users', 'otp_hash'));
        $this->assertFalse(Schema::hasColumn('users', 'otp'));
    }

    public function test_local_registration_keeps_otp_hashed_and_exposes_it_only_through_local_artisan_command(): void
    {
        Mail::fake();
        $this->app['env'] = 'local';
        config(['app.debug' => true]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $attributes = $this->attributes();

        $response = $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $attributes)
            ->assertAccepted()
            ->assertJsonPath('delivery_mode', 'database')
            ->assertJsonPath('message', 'If this email can be registered, a local verification code is available.')
            ->assertJsonMissingPath('otp_code');

        $this->assertDatabaseMissing('users', ['email' => $attributes['email']]);
        $pending = PendingRegistration::where('email', $attributes['email'])->firstOrFail();
        $this->assertTrue(Hash::check($attributes['password'], $pending->password_hash));
        $otp = EmailVerificationOtp::where('email', $attributes['email'])->firstOrFail();
        $this->assertNull($otp->otp_code);
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('auth:local-otp', ['flow' => 'registration', 'email' => $attributes['email']]));
        $output = \Illuminate\Support\Facades\Artisan::output();
        preg_match('/\): (\d{6})/', $output, $matches);
        $this->assertNotEmpty($matches[1] ?? null, $output);
        $this->assertTrue(Hash::check($matches[1], $otp->fresh()->otp_hash));
        Mail::assertNothingSent();

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register/verify', [
                'email' => $attributes['email'],
                'code' => $matches[1],
            ])
            ->assertOk()
            ->assertJsonPath('user.email', $attributes['email'])
            ->assertJsonPath('redirect_to', '/dashboard');

        $user = User::where('email', $attributes['email'])->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseMissing('email_verification_otps', ['email' => $attributes['email']]);
        $this->assertDatabaseMissing('pending_registrations', ['email' => $attributes['email']]);
    }

    public function test_local_resend_replaces_database_code_without_sending_email(): void
    {
        Mail::fake();
        $this->app['env'] = 'local';
        config(['app.debug' => true]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $baseTime = Carbon::now('UTC');
        Carbon::setTestNow($baseTime);
        $attributes = $this->attributes();

        try {
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register', $attributes)
                ->assertAccepted()->assertJsonPath('delivery_mode', 'database');
            $otp = EmailVerificationOtp::where('email', $attributes['email'])->firstOrFail();
            $oldHash = $otp->otp_hash;
            $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('auth:local-otp', ['flow' => 'registration', 'email' => $attributes['email']]));
            preg_match('/\): (\d{6})/', \Illuminate\Support\Facades\Artisan::output(), $first);
            $oldCode = $first[1];

            Carbon::setTestNow($baseTime->copy()->addSeconds(61));
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/resend', ['email' => $attributes['email']])
                ->assertOk()
                ->assertJsonPath('delivery_mode', 'database')
                ->assertJsonPath('message', 'If a pending registration exists, a new local verification code is available.');

            $this->assertNotSame($oldHash, EmailVerificationOtp::where('email', $attributes['email'])->value('otp_hash'));
            $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('auth:local-otp', ['flow' => 'registration', 'email' => $attributes['email']]));
            preg_match('/\): (\d{6})/', \Illuminate\Support\Facades\Artisan::output(), $second);
            $newCode = $second[1];
            $this->assertNotSame($oldCode, $newCode);
            Mail::assertNothingSent();

            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $oldCode])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
            $this->assertDatabaseMissing('users', ['email' => $attributes['email']]);

            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $newCode])
                ->assertOk()->assertJsonPath('user.email', $attributes['email']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_valid_otp_creates_verified_user_logs_in_and_cannot_be_reused(): void
    {
        Mail::fake();
        $attributes = $this->attributes();
        $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/register', $attributes)->assertAccepted();
        $code = $this->sentOtp($attributes['email']);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $code])
            ->assertOk()
            ->assertJsonPath('user.email', $attributes['email'])
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('redirect_to', '/dashboard');

        $user = User::where('email', $attributes['email'])->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check($attributes['password'], $user->password));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseMissing('pending_registrations', ['email' => $attributes['email']]);
        $this->assertDatabaseMissing('email_verification_otps', ['email' => $attributes['email']]);

        Auth::guard('web')->logout();
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ])->assertOk()->assertJsonPath('user.email', $attributes['email']);
        Auth::guard('web')->logout();
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_login_authenticated_refresh_logout_and_protected_route_rejection_journey(): void
    {
        $user = User::factory()->create([
            'email' => 'journey-'.Str::uuid().'@gmail.com',
            'password' => Hash::make('Test-password-123'),
        ]);

        $login = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Test-password-123',
        ])->assertOk();
        $sessionCookie = $login->getCookie(config('session.cookie'), false);
        $this->assertNotNull($sessionCookie);

        Auth::forgetGuards();
        $this->withCredentials()->withUnencryptedCookie(config('session.cookie'), $sessionCookie->getValue())
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/user')->assertOk()->assertJsonPath('email', $user->email);

        Auth::forgetGuards();
        $logout = $this->withCredentials()->withUnencryptedCookie(config('session.cookie'), $sessionCookie->getValue())
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('message', 'Logged out.');
        $logoutCookie = $logout->getCookie(config('session.cookie'), false);
        $this->assertNotNull($logoutCookie);

        Auth::forgetGuards();
        $this->withCredentials()->withUnencryptedCookie(config('session.cookie'), $logoutCookie->getValue())
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_registration_rejects_client_supplied_roles(): void
    {
        $attributes = $this->attributes(['role' => 'admin']);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $attributes)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['email' => $attributes['email']]);
    }

    public function test_registration_does_not_disclose_duplicate_email_addresses_or_create_an_otp(): void
    {
        $existing = User::factory()->create(['email' => 'duplicate-'.Str::uuid().'@gmail.com']);
        $attributes = $this->attributes(['email' => $existing->email]);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $attributes)
            ->assertAccepted()
            ->assertJsonPath('message', 'If this email can be registered, a verification code will be sent.');

        $this->assertSame(1, User::where('email', $existing->email)->count());
        $this->assertDatabaseMissing('pending_registrations', ['email' => $existing->email]);
        $this->assertDatabaseMissing('email_verification_otps', ['email' => $existing->email]);
    }

    public function test_login_uses_a_generic_failure_and_is_rate_limited_by_ip(): void
    {
        $email = 'login-limit-'.Str::uuid().'@gmail.com';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'Wrong-password-123'])
                ->assertUnprocessable()->assertJsonPath('message', trans('auth.failed'));
        }

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'Wrong-password-123'])
            ->assertTooManyRequests();
    }

    public function test_stateful_api_requests_reject_missing_csrf_tokens_outside_testing_runtime(): void
    {
        $this->app['env'] = 'local';

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/password/forgot', ['email' => 'csrf-check-'.Str::uuid().'@gmail.com'])
            ->assertStatus(419);
    }

    public function test_password_change_invalidates_a_previously_authenticated_stateful_session(): void
    {
        $user = User::factory()->create([
            'email' => 'session-revoke-'.Str::uuid().'@gmail.com',
            'password' => Hash::make('Test-password-123'),
        ]);

        $login = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Test-password-123',
        ])->assertOk();

        $sessionCookie = $login->getCookie(config('session.cookie'), false);
        $this->assertNotNull($sessionCookie);
        $user->password = 'Changed-password-456';
        $user->save();
        Auth::forgetGuards();

        $this->withCredentials()->withUnencryptedCookie(config('session.cookie'), $sessionCookie->getValue())
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_registration_enforces_minimum_length_and_never_accepts_a_role(): void
    {
        Mail::fake();
        $attributes = $this->attributes(['password' => 'short77', 'password_confirmation' => 'short77']);
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $attributes)
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $attributes = $this->attributes(['role' => 'moderator']);
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $attributes)
            ->assertUnprocessable()->assertJsonValidationErrors('role');

        Mail::assertNothingSent();
    }

    public function test_registration_accepts_eight_character_passwords_and_long_lowercase_passphrases(): void
    {
        Mail::fake();
        foreach (['sunbeamx', 'blue orchard beside river'] as $password) {
            $attributes = $this->attributes([
                'email' => 'password-'.Str::uuid().'@gmail.com',
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register', $attributes)
                ->assertAccepted();
            $this->assertDatabaseMissing('users', ['email' => $attributes['email']]);
            $pending = PendingRegistration::where('email', $attributes['email'])->firstOrFail();
            $this->assertTrue(Hash::check($password, $pending->password_hash));
        }

        $password128 = substr(str_repeat('long phrase ', 12), 0, 128);
        $attributes = $this->attributes([
            'email' => 'password-'.Str::uuid().'@gmail.com',
            'password' => $password128,
            'password_confirmation' => $password128,
        ]);
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $attributes)
            ->assertAccepted();
        $pending = PendingRegistration::where('email', $attributes['email'])->firstOrFail();
        $differentLastCharacter = substr($password128, 0, -1).(str_ends_with($password128, 'X') ? 'Y' : 'X');
        $this->assertTrue(Hash::check($password128, $pending->password_hash));
        $this->assertFalse(Hash::check($differentLastCharacter, $pending->password_hash));
    }

    public function test_registration_rejects_common_passwords_overlong_passwords_and_mismatched_confirmation(): void
    {
        $tooLong = str_repeat('A', 129);
        $cases = [
            ['password' => 'password123', 'confirmation' => 'password123'],
            ['password' => $tooLong, 'confirmation' => $tooLong],
            ['password' => 'sunbeamx', 'confirmation' => 'sunbeamX'],
        ];

        foreach ($cases as $case) {
            $attributes = $this->attributes([
                'email' => 'password-'.Str::uuid().'@gmail.com',
                'password' => $case['password'],
                'password_confirmation' => $case['confirmation'],
            ]);
            $response = $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register', $attributes);
            $response->assertUnprocessable();
            $this->assertDatabaseMissing('users', ['email' => $attributes['email']]);
            $this->assertDatabaseMissing('pending_registrations', ['email' => $attributes['email']]);
        }
    }

    public function test_wrong_and_expired_otps_are_rejected_and_attempts_are_limited(): void
    {
        Mail::fake();
        $attributes = $this->attributes();
        $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/register', $attributes)->assertAccepted();
        $code = $this->sentOtp($attributes['email']);
        $wrongCode = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $wrongCode])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
        }
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $wrongCode])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseMissing('users', ['email' => $attributes['email']]);
    }

    public function test_expired_pending_registrations_are_purged_without_touching_users(): void
    {
        $existingUser = User::factory()->create();
        $email = 'expired-'.Str::uuid().'@gmail.com';
        $old = now()->subDays(2);

        PendingRegistration::create([
            'name' => 'Expired Pending',
            'email' => $email,
            'password_hash' => Hash::make('Pending-password-123'),
            'expires_at' => $old,
        ]);
        EmailVerificationOtp::create([
            'email' => $email,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => $old,
            'attempts' => 0,
            'last_sent_at' => $old,
        ]);

        $this->artisan('auth:purge-expired-registrations')->assertSuccessful();

        $this->assertDatabaseMissing('pending_registrations', ['email' => $email]);
        $this->assertDatabaseMissing('email_verification_otps', ['email' => $email]);
        $this->assertDatabaseHas('users', ['id' => $existingUser->id, 'email' => $existingUser->email]);
    }

    public function test_expired_code_requires_resend_and_resend_invalidates_the_old_code(): void
    {
        Mail::fake();
        $baseTime = Carbon::now('UTC');
        Carbon::setTestNow($baseTime);
        $attributes = $this->attributes();

        try {
            $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/register', $attributes)->assertAccepted();
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/resend', ['email' => $attributes['email']])
                ->assertOk();
            $codes = Mail::sent(RegistrationOtpMail::class)
                ->filter(fn (RegistrationOtpMail $mail) => $mail->hasTo($attributes['email']))
                ->map(fn (RegistrationOtpMail $mail) => $mail->code)->values()->all();
            $oldCode = $codes[0];

            Carbon::setTestNow($baseTime->copy()->addMinutes(6));
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $oldCode])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
            $this->assertDatabaseMissing('users', ['email' => $attributes['email']]);

            Carbon::setTestNow($baseTime->copy()->addSeconds(61));
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/resend', ['email' => $attributes['email']])
                ->assertOk();
            $codes = Mail::sent(RegistrationOtpMail::class)
                ->filter(fn (RegistrationOtpMail $mail) => $mail->hasTo($attributes['email']))
                ->map(fn (RegistrationOtpMail $mail) => $mail->code)->values()->all();
            $newCode = $codes[1];
            $this->assertNotSame($oldCode, $newCode);

            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $oldCode])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/verify', ['email' => $attributes['email'], 'code' => $newCode])
                ->assertOk()->assertJsonPath('user.email', $attributes['email']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_login_does_not_disclose_an_existing_unverified_account(): void
    {
        $user = User::factory()->unverified()->create(['password' => Hash::make('Test-password-123')]);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Test-password-123'])
            ->assertUnprocessable()
            ->assertJsonPath('message', trans('auth.failed'));

        $this->assertGuest('web');
    }

    public function test_registration_route_is_rate_limited(): void
    {
        Mail::fake();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register', $this->attributes())
                ->assertAccepted();
        }

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $this->attributes())
            ->assertTooManyRequests();
    }

    public function test_resend_route_is_rate_limited_per_ip(): void
    {
        Mail::fake();
        $baseTime = Carbon::now('UTC');
        Carbon::setTestNow($baseTime);

        try {
            $emails = [];
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $attributes = $this->attributes();
                $emails[] = $attributes['email'];
                $this->withHeaders($this->statefulHeaders())
                    ->postJson('/api/v1/auth/register', $attributes)
                    ->assertAccepted();
            }

            Carbon::setTestNow($baseTime->copy()->addSeconds(61));
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $this->withHeaders($this->statefulHeaders())
                    ->postJson('/api/v1/auth/register/resend', ['email' => $emails[$attempt]])
                    ->assertOk();
            }
            $this->withHeaders($this->statefulHeaders())
                ->postJson('/api/v1/auth/register/resend', ['email' => $emails[3]])
                ->assertTooManyRequests();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_user_login_returns_the_server_selected_dashboard_and_user_data(): void
    {
        $user = User::factory()->create([
            'email' => 'login-'.Str::uuid().'@example.test',
            'password' => Hash::make('Test-password-123'),
            'role' => 'user',
        ]);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'Test-password-123',
            ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('redirect_to', '/dashboard');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_without_remember_uses_the_regular_session_only(): void
    {
        $user = User::factory()->create([
            'email' => 'session-'.Str::uuid().'@example.test',
            'password' => Hash::make('Test-password-123'),
            'role' => 'user',
        ]);
        $recallerName = Auth::guard('web')->getRecallerName();
        $rememberToken = $user->remember_token;

        $response = $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'Test-password-123',
                'remember' => false,
            ])
            ->assertOk()
            ->assertJsonPath('redirect_to', '/dashboard')
            ->assertCookieMissing($recallerName);

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame($rememberToken, $user->fresh()->remember_token);
    }

    public function test_remember_login_restores_after_session_is_cleared_and_logout_revokes_the_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'remember-'.Str::uuid().'@example.test',
            'password' => Hash::make('Test-password-123'),
            'role' => 'user',
        ]);
        $recallerName = Auth::guard('web')->getRecallerName();

        $loginResponse = $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'Test-password-123',
                'remember' => true,
            ])
            ->assertOk()
            ->assertJsonPath('redirect_to', '/dashboard')
            ->assertCookieNotExpired($recallerName);

        $recallerCookie = $loginResponse->getCookie($recallerName);
        $wireRecallerCookie = $loginResponse->getCookie($recallerName, false);
        $this->assertNotNull($recallerCookie);
        $this->assertTrue($recallerCookie->isHttpOnly());
        $this->assertSame('lax', $recallerCookie->getSameSite());
        $this->assertGreaterThan(now()->addDays(29)->timestamp, $recallerCookie->getExpiresTime());
        $this->assertNotEmpty($user->fresh()->remember_token);

        // Keep the recaller cookie while dropping session data, as after closing/reopening a browser.
        $this->flushSession();
        Auth::forgetGuards();
        $this->withCredentials();
        $this->withUnencryptedCookie($recallerName, $wireRecallerCookie->getValue())
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $this->assertAuthenticatedAs($user, 'web');
        $rememberToken = $user->fresh()->remember_token;

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertCookieExpired($recallerName);
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token);

        $this->flushSession();
        Auth::forgetGuards();
        $this->withCredentials();
        $this->withUnencryptedCookie($recallerName, $wireRecallerCookie->getValue())
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_signing_into_another_account_revokes_the_previous_remember_session(): void
    {
        $first = User::factory()->create([
            'email' => 'remember-first-'.Str::uuid().'@example.test',
            'password' => Hash::make('First-password-123'),
            'role' => 'user',
        ]);
        $second = User::factory()->create([
            'email' => 'remember-second-'.Str::uuid().'@example.test',
            'password' => Hash::make('Second-password-123'),
            'role' => 'user',
        ]);
        $recallerName = Auth::guard('web')->getRecallerName();

        $firstLogin = $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $first->email,
                'password' => 'First-password-123',
                'remember' => true,
            ])->assertOk();
        $oldRecaller = $firstLogin->getCookie($recallerName, false)->getValue();
        $oldRememberToken = $first->fresh()->remember_token;

        Auth::forgetGuards();
        $this->withCredentials();
        $this->withUnencryptedCookie($recallerName, $oldRecaller)
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $second->email,
                'password' => 'Second-password-123',
                'remember' => false,
            ])
            ->assertOk()
            ->assertJsonPath('user.email', $second->email)
            ->assertCookieExpired($recallerName);

        $this->assertNotSame($oldRememberToken, $first->fresh()->remember_token);
        $this->assertAuthenticatedAs($second, 'web');
    }

    public function test_admin_login_returns_the_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-'.Str::uuid().'@example.test',
            'password' => Hash::make('Test-password-123'),
            'role' => 'admin',
        ]);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $admin->email,
                'password' => 'Test-password-123',
            ])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('redirect_to', '/admin/dashboard');

        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_existing_bcrypt_passwords_remain_valid_after_hash_driver_upgrade(): void
    {
        $password = 'Legacy-password-123';
        $user = User::factory()->create([
            'email' => 'legacy-'.Str::uuid().'@example.test',
            'password' => Hash::make('Temporary-password-123'),
        ]);
        \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update([
            'password' => Hash::driver('bcrypt')->make($password),
        ]);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $response = $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => 'missing-'.Str::uuid().'@example.test',
                'password' => 'incorrect-password',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_logout_ends_the_web_session(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertTrue(Auth::guard('web')->guest());
    }

    public function test_current_user_endpoint_requires_authentication_and_returns_role(): void
    {
        $this->withHeaders($this->statefulHeaders())->getJson('/api/v1/user')->assertUnauthorized();

        $user = User::factory()->create(['role' => 'user']);
        $this->assertSame('active', $user->fresh()->status);
        $response = $this->actingAs($user->fresh(), 'sanctum')
            ->withHeaders($this->statefulHeaders())
            ->getJson('/api/v1/user');
        $response->assertOk()
            ->assertJsonPath('role', 'user');
    }

    public function test_admin_pages_enforce_authentication_and_roles_on_direct_requests(): void
    {
        $this->get('/verify-email')->assertOk();
        $this->get('/verify-email?refresh=1')->assertOk();

        $this->get('/admin/dashboard')->assertRedirect('/login');

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'web')->get('/admin/dashboard')->assertRedirect('/dashboard');

        $moderator = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => ['users.view']]);
        $this->actingAs($moderator, 'web')->get('/admin/dashboard')->assertOk();
        $this->actingAs($moderator, 'web')->get('/admin/users')->assertOk();

        $restrictedModerator = User::factory()->create(['role' => 'moderator']);
        $this->actingAs($restrictedModerator, 'web')->get('/admin/dashboard')->assertForbidden();
        $activityOnlyModerator = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => ['activity.view']]);
        $this->actingAs($activityOnlyModerator, 'web')->get('/admin')->assertRedirect('/admin/activity-logs');
        $this->actingAs($restrictedModerator, 'web')->get('/admin/users')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web')->get('/admin/dashboard')->assertOk();
    }

    public function test_admin_role_management_api_rejects_guests_and_non_admins(): void
    {
        $target = User::factory()->create(['role' => 'user']);
        $endpoint = '/api/v1/admin/users/'.$target->id.'/role';

        $this->withHeaders($this->statefulHeaders())->patchJson($endpoint, ['role' => 'admin'])->assertUnauthorized();

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'web')
            ->withHeaders($this->statefulHeaders())
            ->patchJson($endpoint, ['role' => 'admin'])
            ->assertForbidden();

        $moderator = User::factory()->create(['role' => 'moderator']);
        $this->actingAs($moderator, 'sanctum')
            ->withHeaders($this->statefulHeaders())
            ->patchJson($endpoint, ['role' => 'admin'])
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertSame('admin', $admin->fresh()->role);
        $response = $this->actingAs($admin, 'sanctum')
            ->withHeaders($this->statefulHeaders())
            ->patchJson($endpoint, ['role' => 'admin']);

        $response
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $this->assertSame('admin', $target->fresh()->role);
    }

    public function test_user_routes_redirect_guests_and_allow_moderators_to_view_personal_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');

        $moderator = User::factory()->create(['role' => 'moderator']);
        $this->actingAs($moderator, 'web')->get('/dashboard')->assertOk();

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'web')->get('/dashboard')->assertOk();
    }

    public function test_admin_creation_command_hashes_password_and_does_not_replace_accounts(): void
    {
        $email = 'created-admin-'.Str::uuid().'@example.test';
        $password = 'Admin-Secure-Password-123';

        $this->artisan('planora:admin:create', ['--name' => 'Test Administrator', '--email' => $email])
            ->expectsQuestion('Administrator password', $password)
            ->expectsQuestion('Confirm administrator password', $password)
            ->expectsOutput('Administrator account created. The password was not displayed.')
            ->assertSuccessful();

        $admin = User::where('email', $email)->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check($password, $admin->password));

        $existing = User::factory()->create([
            'email' => 'existing-'.Str::uuid().'@example.test',
            'password' => Hash::make('Existing-password-123'),
            'role' => 'user',
        ]);

        $this->artisan('planora:admin:create', ['--name' => 'Replacement Attempt', '--email' => $existing->email])
            ->expectsOutput('An account with that email already exists. No account was changed.')
            ->assertExitCode(1);

        $this->assertSame('user', $existing->fresh()->role);
        $this->assertTrue(Hash::check('Existing-password-123', $existing->fresh()->password));
    }
}
