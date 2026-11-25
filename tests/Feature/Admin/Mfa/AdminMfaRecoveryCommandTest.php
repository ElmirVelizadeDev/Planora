<?php

namespace Tests\Feature\Admin\Mfa;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class AdminMfaRecoveryCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_recovery_command_is_refused_outside_local_environment(): void
    {
        $admin = User::factory()->create([
            'email' => 'mfa-recovery-'.bin2hex(random_bytes(5)).'@example.test',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $admin->forceFill([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_enabled_at' => now(),
        ])->save();
        $this->assertSame('testing', app()->environment());

        $this->artisan('auth:mfa-reset', ['email' => $admin->email])
            ->expectsOutputToContain('available only in the local environment')
            ->assertExitCode(1);

        $this->assertNotNull($admin->fresh()->mfa_enabled_at);
        $this->assertSame($secret, Crypt::decryptString($admin->fresh()->getRawOriginal('mfa_secret')));
    }

    public function test_recovery_command_rejects_invalid_email_and_non_admin_accounts(): void
    {
        $this->app['env'] = 'local';
        $user = User::factory()->create([
            'email' => 'mfa-user-'.bin2hex(random_bytes(5)).'@example.test',
            'role' => 'user',
        ]);

        $this->artisan('auth:mfa-reset', ['email' => 'not-an-email'])
            ->expectsOutputToContain('Enter a valid administrator email address')
            ->assertExitCode(1);
        $this->artisan('auth:mfa-reset', ['email' => $user->email])
            ->expectsOutputToContain('No eligible administrator account was found')
            ->assertExitCode(1);

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_recovery_command_rejects_non_interactive_execution_before_account_lookup(): void
    {
        $this->app['env'] = 'local';
        $accountLookups = 0;

        DB::listen(function (QueryExecuted $query) use (&$accountLookups): void {
            $sql = strtolower($query->sql);
            if (str_starts_with(ltrim($sql), 'select') && str_contains($sql, 'users') && str_contains($sql, 'email')) {
                $accountLookups++;
            }
        });

        $command = new CommandTester(Artisan::all()['auth:mfa-reset']);
        $exitCode = $command->execute(['email' => 'missing@example.test'], ['interactive' => false]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('requires an interactive terminal', $command->getDisplay());
        $this->assertSame(0, $accountLookups);
    }

    public function test_declined_or_mismatched_confirmation_does_not_change_mfa(): void
    {
        $this->app['env'] = 'local';
        $admin = User::factory()->create([
            'email' => 'mfa-confirm-'.bin2hex(random_bytes(5)).'@example.test',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->forceFill([
            'mfa_secret' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'),
            'mfa_enabled_at' => now(),
        ])->save();
        $enabledAt = $admin->mfa_enabled_at->toDateTimeString();

        $this->artisan('auth:mfa-reset', ['email' => $admin->email])
            ->expectsConfirmation('Reset MFA for this administrator and invalidate their existing sessions?', 'no')
            ->expectsOutputToContain('MFA recovery cancelled')
            ->assertExitCode(1);

        $this->artisan('auth:mfa-reset', ['email' => $admin->email])
            ->expectsConfirmation('Reset MFA for this administrator and invalidate their existing sessions?', 'yes')
            ->expectsQuestion('Re-enter the administrator email address to confirm', 'different@example.test')
            ->expectsOutputToContain('email confirmation did not match')
            ->assertExitCode(1);

        $this->assertSame($enabledAt, $admin->fresh()->mfa_enabled_at->toDateTimeString());
    }

    public function test_recovery_rechecks_admin_eligibility_under_lock_after_account_role_changes(): void
    {
        $this->app['env'] = 'local';
        $admin = User::factory()->create([
            'email' => 'mfa-race-'.bin2hex(random_bytes(5)).'@example.test',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $admin->forceFill([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_enabled_at' => now(),
        ])->save();
        $authVersion = (int) DB::table('users')->where('id', $admin->id)->value('auth_version');
        $rememberToken = $admin->getRememberToken();
        $roleChangedBetweenLookupAndLock = false;

        DB::listen(function (QueryExecuted $query) use ($admin, &$roleChangedBetweenLookupAndLock): void {
            $sql = strtolower($query->sql);
            if (! $roleChangedBetweenLookupAndLock
                && str_starts_with(ltrim($sql), 'select')
                && str_contains($sql, 'users')
                && str_contains($sql, 'email')) {
                $roleChangedBetweenLookupAndLock = true;
                DB::table('users')->where('id', $admin->id)->update(['role' => 'user']);
            }
        });

        $this->artisan('auth:mfa-reset', ['email' => $admin->email])
            ->expectsConfirmation('Reset MFA for this administrator and invalidate their existing sessions?', 'yes')
            ->expectsQuestion('Re-enter the administrator email address to confirm', $admin->email)
            ->expectsOutputToContain('MFA recovery did not complete')
            ->assertExitCode(1);

        $this->assertTrue($roleChangedBetweenLookupAndLock);
        $unchanged = $admin->fresh();
        $this->assertSame('user', $unchanged->role);
        $this->assertSame($secret, Crypt::decryptString($unchanged->getRawOriginal('mfa_secret')));
        $this->assertNotNull($unchanged->mfa_enabled_at);
        $this->assertSame($authVersion, (int) $unchanged->auth_version);
        $this->assertSame($authVersion, (int) DB::table('users')->where('id', $admin->id)->value('auth_version'));
        $this->assertSame($rememberToken, $unchanged->getRememberToken());
    }

    public function test_reset_invalidates_only_target_mfa_sessions_and_allows_secure_reenrollment(): void
    {
        $headers = ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
        $admin = User::factory()->create([
            'email' => 'mfa-target-'.bin2hex(random_bytes(5)).'@example.test',
            'password' => 'Admin-mfa-recovery-123!',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'remember_token' => 'old-target-remember-token',
        ]);
        $otherAdmin = User::factory()->create([
            'email' => 'mfa-other-'.bin2hex(random_bytes(5)).'@example.test',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $oldSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $otherSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $admin->forceFill([
            'mfa_secret' => Crypt::encryptString($oldSecret),
            'mfa_enabled_at' => now(),
            'mfa_last_counter' => intdiv(now()->timestamp, 30),
        ])->save();
        $otherAdmin->forceFill([
            'mfa_secret' => Crypt::encryptString($otherSecret),
            'mfa_enabled_at' => now(),
        ])->save();
        $targetToken = $admin->createToken('target-token');
        $otherToken = $otherAdmin->createToken('other-token');
        foreach ([[$admin, 'TARGETCODE1'], [$otherAdmin, 'OTHERCODE01']] as [$account, $code]) {
            DB::table('admin_mfa_recovery_codes')->insert([
                'user_id' => $account->id,
                'code_hash' => bcrypt($code),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('admin_mfa_attempts')->insert([
                'user_id' => $account->id,
                'flow' => 'login',
                'challenge_key' => hash('sha256', $code),
                'attempts' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $originalPasswordHash = $admin->getAuthPassword();
        $originalVersion = (int) DB::table('users')->where('id', $admin->id)->value('auth_version');

        $this->withHeaders($headers)->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Admin-mfa-recovery-123!',
        ])->assertOk()->assertJsonPath('mfa_required', true)->assertJsonMissingPath('user');

        $this->app['env'] = 'local';
        try {
            $this->artisan('auth:mfa-reset', ['email' => $admin->email])
                ->expectsConfirmation('Reset MFA for this administrator and invalidate their existing sessions?', 'yes')
                ->expectsQuestion('Re-enter the administrator email address to confirm', $admin->email)
                ->expectsOutputToContain('MFA remains mandatory')
                ->assertExitCode(0);
        } finally {
            $this->app['env'] = 'testing';
        }

        $reset = $admin->fresh();
        $this->assertNull($reset->mfa_secret);
        $this->assertNull($reset->mfa_enabled_at);
        $this->assertNull($reset->mfa_enrollment_expires_at);
        $this->assertNull($reset->mfa_last_counter);
        $this->assertSame($originalVersion + 1, (int) DB::table('users')->where('id', $admin->id)->value('auth_version'));
        $this->assertNotSame('old-target-remember-token', $reset->getRememberToken());
        $this->assertSame($originalPasswordHash, $reset->getAuthPassword());
        $this->assertSame($admin->email, $reset->email);
        $this->assertSame('admin', $reset->role);
        $this->assertSame('active', $reset->status);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $targetToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
        $this->assertDatabaseMissing('admin_mfa_recovery_codes', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('admin_mfa_recovery_codes', ['user_id' => $otherAdmin->id]);
        $this->assertDatabaseMissing('admin_mfa_attempts', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('admin_mfa_attempts', ['user_id' => $otherAdmin->id]);
        $this->assertSame($otherSecret, Crypt::decryptString($otherAdmin->fresh()->getRawOriginal('mfa_secret')));
        $this->assertNotNull($otherAdmin->fresh()->mfa_enabled_at);

        $this->withHeaders($headers)->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => $this->currentTotpCode($oldSecret)])
            ->assertUnauthorized();
        $this->assertGuest('web');

        $this->withHeaders($headers)->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Admin-mfa-recovery-123!',
        ])->assertOk()->assertJsonPath('mfa_enrollment_required', true)->assertJsonMissingPath('user');

        $setup = $this->withHeaders($headers)->postJson('/api/v1/auth/admin/mfa/enroll-login')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['qr_code_data_uri', 'expires_at'])
            ->assertJsonMissingPath('secret')->assertJsonMissingPath('otpauth_uri');
        $this->assertStringStartsWith('data:image/png;base64,', $setup->json('qr_code_data_uri'));
        $newSecret = Crypt::decryptString($admin->fresh()->getRawOriginal('mfa_secret'));
        $this->assertNotSame($oldSecret, $newSecret);

        $this->withHeaders($headers)->postJson('/api/v1/auth/admin/mfa/enroll-login/verify', ['code' => '000000'])
            ->assertUnprocessable();
        $this->assertNull($admin->fresh()->mfa_enabled_at);
        $this->withHeaders($headers)->postJson('/api/v1/auth/admin/mfa/enroll-login/verify', ['code' => $this->currentTotpCode($newSecret)])
            ->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonPath('redirect_to', '/admin/dashboard')
            ->assertJsonCount(10, 'recovery_codes');
        $this->assertNotNull($admin->fresh()->mfa_enabled_at);
        $this->assertSame(10, DB::table('admin_mfa_recovery_codes')->where('user_id', $admin->id)->count());
        $this->assertAuthenticatedAs($admin, 'web');
    }
}
