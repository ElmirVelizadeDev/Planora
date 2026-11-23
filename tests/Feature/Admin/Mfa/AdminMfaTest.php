<?php

namespace Tests\Feature\Admin\Mfa;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMfaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_password_login_for_mfa_admin_creates_only_a_short_lived_unauthenticated_challenge(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now(), 'password' => 'Mfa-test-password-123!']);
        $admin->forceFill(['mfa_secret' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'), 'mfa_enabled_at' => now()])->save();

        $response = $this->withHeaders(['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'])
            ->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'Mfa-test-password-123!'])
            ->assertOk()->assertJsonPath('mfa_required', true)->assertJsonMissingPath('user');
        $this->assertGuest('web');
        $this->assertNotSame($admin->id, auth('web')->id());
    }

    public function test_admin_without_mfa_is_not_authenticated_and_repeated_failures_invalidate_challenge(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now(), 'password' => 'Mfa-test-password-123!']);
        $headers = ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];

        $this->withHeaders($headers)->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'Mfa-test-password-123!'])
            ->assertOk()->assertJsonPath('mfa_enrollment_required', true)->assertJsonMissingPath('user');
        $this->assertGuest('web');
        $this->getJson('/api/v1/user')->assertUnauthorized();

        $admin->forceFill(['mfa_secret' => Crypt::encryptString($secret), 'mfa_enabled_at' => now()])->save();
        $this->withHeaders($headers)->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'Mfa-test-password-123!'])
            ->assertJsonPath('mfa_required', true);
        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => '000000'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => '000000'])->assertStatus(429);
        $this->assertGuest('web');
        $this->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => $this->totp($secret)])->assertUnauthorized();
    }

    public function test_totp_completes_admin_login_once_and_rejects_replay(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now(), 'password' => 'Mfa-test-password-123!']);
        $admin->forceFill(['mfa_secret' => Crypt::encryptString($secret), 'mfa_enabled_at' => now()])->save();
        $headers = ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];

        $this->withHeaders($headers)->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'Mfa-test-password-123!'])->assertJsonPath('mfa_required', true);
        $code = $this->totp($secret);
        $this->withHeaders($headers)->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => $code])->assertOk()->assertJsonPath('user.role', 'admin');
        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->withHeaders($headers)->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'Mfa-test-password-123!'])->assertJsonPath('mfa_required', true);
        $this->withHeaders($headers)->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => $code])->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_recovery_codes_remain_hashed_single_use_and_authenticate_once(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now(), 'password' => 'Mfa-test-password-123!']);
        $admin->forceFill(['mfa_secret' => Crypt::encryptString($secret), 'mfa_enabled_at' => now()])->save();
        $recovery = 'A1B2C3D4E5';
        DB::table('admin_mfa_recovery_codes')->insert(['user_id' => $admin->id, 'code_hash' => Hash::make($recovery), 'created_at' => now(), 'updated_at' => now()]);

        $login = ['email' => $admin->email, 'password' => 'Mfa-test-password-123!'];
        $headers = ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
        $this->withHeaders($headers)->postJson('/api/v1/auth/login', $login)->assertJsonPath('mfa_required', true);
        $this->withHeaders($headers)->postJson('/api/v1/auth/admin/mfa/verify-login', ['recovery_code' => $recovery])->assertOk()->assertJsonPath('user.role', 'admin');
        $this->assertNotNull(DB::table('admin_mfa_recovery_codes')->where('user_id', $admin->id)->value('used_at'));
        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->withHeaders($headers)->postJson('/api/v1/auth/login', $login)->assertJsonPath('mfa_required', true);
        $this->withHeaders($headers)->postJson('/api/v1/auth/admin/mfa/verify-login', ['recovery_code' => $recovery])->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_ordinary_user_cannot_use_admin_mfa_management_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/mfa')->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/mfa/enrollment', ['password' => 'password'])->assertForbidden();
    }

    public function test_only_authenticated_active_admins_can_obtain_enrollment_qr_data(): void
    {
        $headers = ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
        $this->withHeaders($headers)->postJson('/api/v1/admin/mfa/enrollment', ['password' => 'password'])->assertUnauthorized();

        $inactiveAdmin = User::factory()->create(['role' => 'admin', 'status' => 'inactive', 'password' => 'Mfa-test-password-123!']);
        $this->actingAs($inactiveAdmin, 'sanctum')->withHeaders($headers)
            ->postJson('/api/v1/admin/mfa/enrollment', ['password' => 'Mfa-test-password-123!'])->assertForbidden();

        $user = User::factory()->create(['role' => 'user', 'status' => 'active', 'password' => 'Mfa-test-password-123!']);
        $this->actingAs($user, 'web')->withHeaders($headers)
            ->postJson('/api/v1/admin/mfa/enrollment', ['password' => 'Mfa-test-password-123!'])->assertUnauthorized();
    }

    public function test_admin_without_mfa_must_complete_private_enrollment_before_authentication(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'password' => 'Mfa-test-password-123!']);
        $this->withHeaders(['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'])
            ->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'Mfa-test-password-123!'])
            ->assertOk()->assertJsonPath('mfa_enrollment_required', true)->assertJsonMissingPath('user');
        $started = $this->postJson('/api/v1/auth/admin/mfa/enroll-login')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['qr_code_data_uri', 'expires_at'])->assertJsonMissingPath('secret')->assertJsonMissingPath('otpauth_uri');
        $this->assertStringStartsWith('data:image/png;base64,', $started->json('qr_code_data_uri'));
        $secret = Crypt::decryptString($admin->fresh()->getRawOriginal('mfa_secret'));
        $this->assertNotSame($secret, $admin->fresh()->getRawOriginal('mfa_secret'));
        $this->assertNotSame($admin->email, $started->json('qr_code_data_uri'));

        $this->postJson('/api/v1/auth/admin/mfa/enroll-login/verify', ['code' => '000000'])->assertUnprocessable();
        $this->assertNull($admin->fresh()->mfa_enabled_at);
        $enabled = $this->postJson('/api/v1/auth/admin/mfa/enroll-login/verify', ['code' => $this->totp($secret)])
            ->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonPath('redirect_to', '/admin/dashboard')->assertJsonCount(10, 'recovery_codes');
        $codes = $enabled->json('recovery_codes');
        $this->assertDatabaseCount('admin_mfa_recovery_codes', 10);
        $this->assertTrue(Hash::check($codes[0], DB::table('admin_mfa_recovery_codes')->where('user_id', $admin->id)->value('code_hash')));
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/user')
            ->assertJsonMissingPath('data.mfa_secret')->assertJsonMissingPath('data.recovery_codes')
            ->assertJsonMissingPath('data.qr_code_data_uri');
    }

    public function test_critical_admin_mutation_requires_step_up_before_business_logic_runs(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $target = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/users/'.$target->id.'/role', ['role' => 'moderator'])
            ->assertStatus(428)->assertJsonPath('purpose', 'roles.manage');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'user']);
    }

    public function test_step_up_grant_is_one_use_and_bound_to_the_current_session_and_purpose(): void
    {
        $headers = ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'password' => 'Mfa-test-password-123!']);
        $this->actingAs($admin, 'web')->withHeaders($headers)->withCredentials();
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $admin->forceFill(['mfa_secret' => Crypt::encryptString($secret), 'mfa_enabled_at' => now()])->save();
        $this->postJson('/api/v1/admin/step-up/verify', ['purpose' => 'roles.manage', 'code' => $this->totp($secret)])->assertOk();
        $this->grantAdminStepUp('roles.manage', $admin);
        $this->actingAs($admin, 'sanctum')->withHeaders($headers)->withCredentials();
        $target = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->patchJson('/api/v1/admin/users/'.$target->id.'/role', ['role' => 'moderator'])->assertOk();
        $this->patchJson('/api/v1/admin/users/'.$target->id.'/role', ['role' => 'admin'])->assertStatus(428);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'moderator']);
    }

    private function totp(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $char) $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        $key = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $key .= chr(bindec($byte));
        $counter = intdiv(now()->timestamp, 30);
        $digest = hash_hmac('sha1', pack('N2', 0, $counter), $key, true);
        $offset = ord($digest[19]) & 0x0f;
        $binary = ((ord($digest[$offset]) & 0x7f) << 24) | ((ord($digest[$offset + 1]) & 0xff) << 16)
            | ((ord($digest[$offset + 2]) & 0xff) << 8) | (ord($digest[$offset + 3]) & 0xff);
        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
