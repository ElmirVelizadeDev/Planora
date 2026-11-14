<?php

namespace Tests\Feature\Admin\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('settings'), 'Run the settings migration against the isolated test database before this test.');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_settings_management_is_admin_only(): void
    {
        $this->getJson('/api/v1/admin/settings')->assertUnauthorized();
        foreach (['user', 'moderator'] as $role) {
            $account = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($account, 'sanctum')->getJson('/api/v1/admin/settings')->assertForbidden();
            $this->getJson('/api/v1/admin/settings/security')->assertForbidden();
            $this->putJson('/api/v1/admin/settings/security', [])->assertForbidden();
            $this->postJson('/api/v1/admin/settings/email/test', ['recipient' => 'admin@example.test'])->assertForbidden();
        }
        $this->actingAs($this->admin(), 'sanctum')->getJson('/api/v1/admin/settings')->assertOk();
    }

    public function test_admin_can_persist_general_settings_and_reject_invalid_regional_values(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum');
        $values = [
            'application_name' => 'Planora Workspace', 'workspace_name' => 'Product Studio',
            'workspace_description' => 'Planning across product teams.', 'contact_email' => 'contact@example.test',
            'default_language' => 'en', 'timezone' => 'Asia/Baku', 'date_format' => 'd/m/Y', 'time_format' => '24h',
        ];
        $this->putJson('/api/v1/admin/settings/general', $values)->assertOk()->assertJsonPath('data.workspace_name', 'Product Studio');
        $this->assertDatabaseHas('settings', ['key' => 'workspace_name', 'value' => 'Product Studio']);
        $this->getJson('/api/v1/admin/settings/general')->assertOk()->assertJsonPath('data.workspace_name', 'Product Studio');
        $this->putJson('/api/v1/admin/settings/general', [...$values, 'timezone' => 'Not/A_Real_Zone'])->assertUnprocessable();
        $this->putJson('/api/v1/admin/settings/general', [...$values, 'date_format' => 'Y-m-d H:i:s e'])->assertUnprocessable();
    }

    public function test_settings_groups_persist_independently_and_reset_to_registry_defaults(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/v1/admin/settings/general', [
            'application_name' => 'Planora Workspace', 'workspace_name' => 'Product Studio',
            'workspace_description' => 'Planning across product teams.', 'contact_email' => 'contact@example.test',
            'default_language' => 'en', 'timezone' => 'Asia/Baku', 'date_format' => 'd/m/Y', 'time_format' => '24h',
        ])->assertOk();

        $this->putJson('/api/v1/admin/settings/security', [
            'minimum_password_length' => 12, 'require_uppercase' => true, 'require_lowercase' => true,
            'require_number' => true, 'require_special_character' => false, 'max_login_attempts' => 6,
            'login_lockout_minutes' => 5, 'otp_expiration_minutes' => 8, 'otp_max_attempts' => 4,
            'otp_resend_cooldown_seconds' => 90,
        ])->assertOk()->assertJsonPath('data.minimum_password_length', 12);

        $this->getJson('/api/v1/admin/settings/general')
            ->assertOk()->assertJsonPath('data.workspace_name', 'Product Studio')->assertJsonPath('data.timezone', 'Asia/Baku');
        $this->assertDatabaseHas('settings', ['key' => 'workspace_name', 'value' => 'Product Studio']);

        $defaults = app(\App\Services\Settings\SettingsRegistry::class)->group('security');
        $this->postJson('/api/v1/admin/settings/security/reset')
            ->assertOk()->assertJsonPath('data.minimum_password_length', $defaults['minimum_password_length']['default']);
        $this->getJson('/api/v1/admin/settings/security')->assertOk()
            ->assertJsonPath('data.minimum_password_length', $defaults['minimum_password_length']['default']);
        $this->assertDatabaseHas('settings', ['key' => 'workspace_name', 'value' => 'Product Studio']);
    }

    public function test_security_policy_is_persisted_and_used_for_registration_password_validation(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/v1/admin/settings/security', [
            'minimum_password_length' => 12, 'require_uppercase' => true, 'require_lowercase' => true,
            'require_number' => true, 'require_special_character' => false, 'max_login_attempts' => 6,
            'login_lockout_minutes' => 5, 'otp_expiration_minutes' => 8, 'otp_max_attempts' => 4,
            'otp_resend_cooldown_seconds' => 90,
        ])->assertOk();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Account', 'email' => 'security-policy@gmail.com',
            'password' => 'lowercase1234', 'password_confirmation' => 'lowercase1234', 'terms_accepted' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Account', 'email' => 'security-policy@gmail.com',
            'password' => 'Uppercase1234', 'password_confirmation' => 'Uppercase1234', 'terms_accepted' => true,
        ])->assertAccepted();
    }

    public function test_security_policy_applies_to_authenticated_admin_password_changes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/admin/settings/security', [
            'minimum_password_length' => 14, 'require_uppercase' => false, 'require_lowercase' => false,
            'require_number' => false, 'require_special_character' => true, 'max_login_attempts' => 10,
            'login_lockout_minutes' => 1, 'otp_expiration_minutes' => 5, 'otp_max_attempts' => 5,
            'otp_resend_cooldown_seconds' => 60,
        ])->assertOk();

        $this->postJson('/api/v1/admin/users/'.$admin->id.'/password', [
            'current_password' => 'password', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/admin/users/'.$admin->id.'/password', [
            'current_password' => 'password', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
        ])->assertOk();
    }

    public function test_email_password_is_encrypted_and_never_returned_by_settings_api(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/admin/settings/email', [
            'mail_mailer' => 'smtp', 'mail_host' => 'smtp.example.test', 'mail_port' => 587,
            'mail_encryption' => 'tls', 'mail_username' => 'mailer-user', 'mail_password' => 'SensitiveCredential!23',
            'mail_from_address' => 'planora@example.test', 'mail_from_name' => 'Planora',
        ])->assertOk()->assertJsonMissing(['mail_password' => 'SensitiveCredential!23'])->assertJsonPath('data.mail_password_configured', true);

        $stored = Setting::query()->where('key', 'mail_password')->firstOrFail();
        $this->assertTrue($stored->is_sensitive);
        $this->assertNotSame('SensitiveCredential!23', $stored->value);
        $this->assertSame('SensitiveCredential!23', Crypt::decryptString($stored->value));
        $this->getJson('/api/v1/admin/settings/email')->assertOk()->assertJsonMissing(['mail_password' => 'SensitiveCredential!23']);

        $this->putJson('/api/v1/admin/settings/email', [
            'mail_mailer' => 'smtp', 'mail_host' => 'smtp.example.test', 'mail_port' => 587,
            'mail_encryption' => 'tls', 'mail_username' => 'mailer-user', 'mail_password' => '',
            'mail_from_address' => 'planora@example.test', 'mail_from_name' => 'Planora',
        ])->assertOk();
        $this->assertSame('SensitiveCredential!23', Crypt::decryptString($stored->fresh()->value));
    }

    public function test_logo_upload_and_removal_use_the_public_branding_disk(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin(), 'sanctum');
        $response = $this->postJson('/api/v1/admin/settings/general/logo', [
            'logo' => UploadedFile::fake()->image('workspace.webp', 128, 128),
        ])->assertOk();

        $url = $response->json('data.application_logo_url');
        $this->assertStringContainsString('/storage/settings/branding/', $url);
        $setting = Setting::query()->where('key', 'application_logo')->firstOrFail();
        Storage::disk('public')->assertExists($setting->value);

        $this->deleteJson('/api/v1/admin/settings/general/logo')->assertOk();
        Storage::disk('public')->assertMissing($setting->value);
    }

    public function test_invalid_email_transport_and_test_recipient_are_rejected(): void
    {
        $this->actingAs($this->admin(), 'sanctum');
        $this->putJson('/api/v1/admin/settings/email', [
            'mail_mailer' => 'smtp', 'mail_host' => 'https://bad host/path', 'mail_port' => 70000,
            'mail_encryption' => 'starttls', 'mail_username' => '', 'mail_password' => '',
            'mail_from_address' => 'not-an-email', 'mail_from_name' => 'Planora',
        ])->assertUnprocessable()->assertJsonValidationErrors(['mail_host', 'mail_port', 'mail_encryption', 'mail_from_address']);
        $this->postJson('/api/v1/admin/settings/email/test', ['recipient' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('recipient');
    }

    public function test_test_email_uses_the_mail_transport_and_reports_acceptance(): void
    {
        Mail::shouldReceive('purge')->zeroOrMoreTimes();
        Mail::shouldReceive('raw')->once()->andReturn(null);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/settings/email/test', ['recipient' => 'admin@example.test'])
            ->assertOk()
            ->assertJsonPath('message', 'The mail transport accepted the test email.');
    }

    public function test_test_email_failure_returns_a_safe_error_without_transport_details(): void
    {
        Mail::shouldReceive('purge')->zeroOrMoreTimes();
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('private transport credential'));

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/settings/email/test', ['recipient' => 'admin@example.test'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The configured mail transport could not send the test email. Verify the mail settings and try again.')
            ->assertJsonMissing(['message' => 'private transport credential']);
    }
}
