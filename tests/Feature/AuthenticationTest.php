<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'email' => 'auth-'.Str::uuid().'@example.test',
            'password' => 'Test-password-123',
            'password_confirmation' => 'Test-password-123',
        ], $overrides);
    }

    public function test_registration_persists_a_hashed_user_role_account(): void
    {
        $attributes = $this->attributes();

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $attributes)
            ->assertCreated()
            ->assertJsonPath('user.email', $attributes['email'])
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('redirect_to', '/dashboard');

        $user = User::where('email', $attributes['email'])->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertNotSame($attributes['password'], $user->password);
        $this->assertTrue(Hash::check($attributes['password'], $user->password));
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

    public function test_registration_rejects_duplicate_email_addresses(): void
    {
        $existing = User::factory()->create();
        $attributes = $this->attributes(['email' => $existing->email]);

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/register', $attributes)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, User::where('email', $existing->email)->count());
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
        $this->get('/admin/dashboard')->assertRedirect('/login');

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'web')->get('/admin/dashboard')->assertRedirect('/dashboard');

        $moderator = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => ['users.view']]);
        $this->actingAs($moderator, 'web')->get('/admin/dashboard')->assertRedirect('/admin/users');
        $this->actingAs($moderator, 'web')->get('/admin/users')->assertOk();

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
