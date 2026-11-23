<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministratorEntryTest extends TestCase
{
    use DatabaseTransactions;

    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(MaintenanceScopeStore::class)->reset();
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }
    }

    protected function tearDown(): void
    {
        app(MaintenanceScopeStore::class)->disableAll();
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }
        parent::tearDown();
    }

    public function test_secret_entry_is_server_registered_and_not_rendered_on_public_pages(): void
    {
        $path = (string) config('planora.admin_entry_path');
        $this->assertGreaterThanOrEqual(48, strlen($path));

        $this->get('/')->assertOk()->assertDontSee($path);
        $this->getJson('/api/v1/maintenance/status')->assertOk()
            ->assertJsonMissingPath('data.admin_entry_path')
            ->assertDontSee($path);
        $this->get('/'.$path)->assertOk()
            ->assertSee('data-planora-view="admin-auth"', false)
            ->assertDontSee($path)
            ->assertCookie('XSRF-TOKEN')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/admin/system-maintenance')->assertUnauthorized()->assertDontSee($path);
    }

    public function test_admin_can_reauthenticate_and_use_workspace_with_all_planora_scopes_enabled(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-entry-'.Str::uuid().'@example.test',
            'password' => Hash::make('Admin-entry-password-123'),
            'role' => 'admin',
        ]);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $admin->forceFill(['mfa_secret' => Crypt::encryptString($secret), 'mfa_enabled_at' => now()])->save();
        $store = app(MaintenanceScopeStore::class);
        $path = '/'.config('planora.admin_entry_path');

        // Sign in before enabling every independent Planora scope.
        $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Admin-entry-password-123',
        ])->assertOk()->assertJsonPath('mfa_required', true);
        $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => $this->currentTotpCode($secret)])->assertOk();
        $this->assertAuthenticatedAs($admin, 'web');
        $store->setAll(true);

        $this->get('/')->assertStatus(503);
        $this->get('/login')->assertStatus(503);
        $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Admin-entry-password-123',
        ])->assertStatus(503);
        $this->get($path)->assertOk()->assertSee('data-planora-view="admin-auth"', false);

        $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertGuest('web');

        $this->get($path)->assertOk();
        $anonymousSessionId = app('session')->driver()->getId();
        $this->postJson($path, [
            'email' => $admin->email,
            'password' => 'Admin-entry-password-123',
        ])->assertOk()->assertJsonPath('mfa_required', true)->assertJsonMissingPath('user');
        $this->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => $this->currentTotpCode($secret)])->assertStatus(503);
        $this->assertGuest('web');

        // Authentication maintenance continues to block MFA verification; a
        // scoped outage can be lifted without changing global maintenance state.
        $store->setAll(false);
        $this->postJson($path, ['email' => $admin->email, 'password' => 'Admin-entry-password-123'])
            ->assertOk()->assertJsonPath('mfa_required', true);
        $this->travel(31)->seconds();
        $login = $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => $this->currentTotpCode($secret)])
            ->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonPath('redirect_to', '/admin/dashboard')->assertDontSee($path);

        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNotSame($anonymousSessionId, app('session')->driver()->getId());
        $this->assertNotNull($login->getCookie(config('session.cookie'), false));
        $this->travelBack();
        $store->setAll(true);
        // Simulate Laravel's fresh AuthManager state on the next browser request.
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->get('/admin/dashboard')->assertOk();
        $this->get('/admin/system-maintenance')->assertOk();
        $this->withHeaders($this->statefulHeaders())->getJson('/api/v1/admin/system-maintenance')->assertOk();
        $this->assertSame([
            'public_website' => true,
            'authentication' => true,
            'dashboard_workspace' => true,
        ], $store->read()['scopes']);

        $this->withHeaders($this->statefulHeaders())->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertGuest('web');
        $this->postJson($path, [
            'email' => $admin->email,
            'password' => 'Admin-entry-password-123',
        ])->assertOk()->assertJsonPath('mfa_required', true)->assertJsonMissingPath('user');
        $this->assertGuest('web');
        $this->assertSame(true, $store->read()['scopes']['dashboard_workspace']);

        $this->assertDatabaseHas('user_activity_logs', [
            'event' => 'auth.admin_mfa_challenge_started',
            'actor_id' => $admin->id,
            'request_path' => '/[protected-admin-entry]',
        ]);
    }

    public function test_secret_entry_rejects_invalid_credentials_non_admins_and_ineligible_accounts(): void
    {
        $path = '/'.config('planora.admin_entry_path');
        $regularUser = User::factory()->create([
            'email' => 'regular-entry-'.Str::uuid().'@example.test',
            'password' => Hash::make('Regular-entry-password-123'),
            'role' => 'user',
        ]);
        $moderator = User::factory()->create([
            'email' => 'moderator-entry-'.Str::uuid().'@example.test',
            'password' => Hash::make('Moderator-entry-password-123'),
            'role' => 'moderator',
            'moderator_permissions' => ['users.view', 'tasks.view', 'projects.view', 'reports.view', 'activity.view'],
        ]);
        $inactiveAdmin = User::factory()->create([
            'email' => 'inactive-admin-'.Str::uuid().'@example.test',
            'password' => Hash::make('Admin-entry-password-123'),
            'role' => 'admin',
            'status' => 'inactive',
        ]);
        $bannedAdmin = User::factory()->create([
            'email' => 'banned-admin-'.Str::uuid().'@example.test',
            'password' => Hash::make('Admin-entry-password-123'),
            'role' => 'admin',
            'status' => 'banned',
        ]);
        $deletedAdmin = User::factory()->create([
            'email' => 'deleted-admin-'.Str::uuid().'@example.test',
            'password' => Hash::make('Admin-entry-password-123'),
            'role' => 'admin',
        ]);
        $deletedAdmin->delete();

        foreach ([
            ['email' => $regularUser->email, 'password' => 'Regular-entry-password-123'],
            ['email' => $moderator->email, 'password' => 'Moderator-entry-password-123'],
            ['email' => $inactiveAdmin->email, 'password' => 'Admin-entry-password-123'],
            ['email' => $bannedAdmin->email, 'password' => 'Admin-entry-password-123'],
            ['email' => 'missing-'.Str::uuid().'@example.test', 'password' => 'Wrong-password-123'],
            ['email' => $deletedAdmin->email, 'password' => 'Admin-entry-password-123'],
        ] as $credentials) {
            $this->postJson($path, $credentials)->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->assertGuest('web');
        }
    }

    public function test_secret_entry_uses_web_csrf_protection_and_the_existing_login_rate_limiter(): void
    {
        $path = '/'.config('planora.admin_entry_path');

        $this->app['env'] = 'local';
        $this->postJson($path, [])->assertStatus(419);
        $this->assertGuest('web');
        $this->app['env'] = 'testing';

        RateLimiter::clear('auth-login-ip:10.42.0.77');
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.42.0.77'])
                ->postJson($path, [])
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.42.0.77'])
            ->postJson($path, [])
            ->assertTooManyRequests();
        $this->assertGuest('web');
    }

    public function test_secret_entry_is_exempt_from_laravel_global_maintenance_but_still_requires_admin_credentials(): void
    {
        $admin = User::factory()->create([
            'email' => 'native-maintenance-'.Str::uuid().'@example.test',
            'password' => Hash::make('Admin-entry-password-123'),
            'role' => 'admin',
        ]);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $admin->forceFill(['mfa_secret' => Crypt::encryptString($secret), 'mfa_enabled_at' => now()])->save();
        $path = '/'.config('planora.admin_entry_path');
        $headers = ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
        $maintenance = app()->maintenanceMode();
        $maintenance->activate(['retry' => 60]);

        try {
            $this->withHeaders($headers)->withCredentials()->get($path)->assertOk()->assertSee('data-planora-view="admin-auth"', false);
            $this->withHeaders($headers)->withCredentials()->postJson($path, [
                'email' => $admin->email,
                'password' => 'not-the-password',
            ])->assertUnprocessable();
            $this->assertGuest('web');

            $this->withHeaders($headers)->withCredentials()->postJson($path, [
                'email' => $admin->email,
                'password' => 'Admin-entry-password-123',
            ])->assertOk()->assertJsonPath('mfa_required', true)->assertJsonMissingPath('user');
            $this->withHeaders($headers)->withCredentials()->postJson('/api/v1/auth/admin/mfa/verify-login', ['code' => $this->currentTotpCode($secret)])
                ->assertOk()->assertJsonPath('user.role', 'admin');
            $this->assertAuthenticatedAs($admin, 'web');
        } finally {
            if ($maintenance->active()) {
                Artisan::call('up');
            }
        }
    }
}
