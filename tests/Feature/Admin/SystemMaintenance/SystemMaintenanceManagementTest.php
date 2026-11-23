<?php

namespace Tests\Feature\Admin\SystemMaintenance;

use App\Models\User;
use App\Services\Api\V1\Dashboard\DashboardDataService;
use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SystemMaintenanceManagementTest extends TestCase
{
    use DatabaseTransactions;

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
        if (app()->isDownForMaintenance()) Artisan::call('planora:maintenance-up');

        parent::tearDown();
    }

    public function test_only_an_administrator_can_read_maintenance_status(): void
    {
        $this->getJson('/api/v1/admin/system-maintenance')->assertUnauthorized();

        $user = $this->account('user', 501);
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/system-maintenance')
            ->assertForbidden();

        $admin = $this->account('admin', 502);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/system-maintenance')
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.native_active', false)
            ->assertJsonPath('data.overall', 'operational')
            ->assertJsonPath('data.message', config('planora.maintenance.message'))
            ->assertJsonMissingPath('data.secret');
    }

    public function test_admin_can_enable_and_disable_all_scopes_through_the_panel_without_native_global_maintenance(): void
    {
        $maintenanceMode = app()->maintenanceMode();
        $this->assertFalse($maintenanceMode->active(), 'The feature test must not start while maintenance is active.');

        $admin = $this->account('admin', 503);
        $user = $this->account('user', 504);
        $this->actingAs($admin, 'sanctum');

        try {
            $this->grantAdminStepUp('system.maintenance', $admin);
            $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/system-maintenance/enable', [
                'message' => 'Planora is receiving a scheduled update.',
                'estimated_at' => '2099-01-01T18:00:00+00:00',
                'retry_after' => 75,
            ])->assertOk()
                ->assertJsonPath('data.active', true)
                ->assertJsonPath('data.native_active', false)
                ->assertJsonPath('data.overall', 'full_maintenance')
                ->assertJsonPath('data.scopes.public_website', true)
                ->assertJsonPath('data.scopes.authentication', true)
                ->assertJsonPath('data.scopes.dashboard_workspace', true)
                ->assertJsonPath('data.message', 'Planora is receiving a scheduled update.')
                ->assertJsonPath('data.estimated_at', '2099-01-01T18:00:00+00:00')
                ->assertJsonPath('data.retry_after', 75)
                ->assertJsonMissingPath('data.secret');

            $this->assertFalse($maintenanceMode->active(), 'Independent scopes must not activate Laravel global maintenance.');
            $this->get('/')->assertStatus(503);
            $this->get('/login')->assertStatus(503);
            $this->postJson('/api/v1/auth/login', [])->assertStatus(503);
            $this->actingAs($admin, 'web')->get('/admin/dashboard')->assertOk();

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/admin/system-maintenance')
                ->assertStatus(503);
            $this->postJson('/api/v1/admin/system-maintenance/disable')->assertStatus(503);
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/user')
                ->assertStatus(503);

            $this->actingAs($admin, 'web')
                ->get('/admin/system-maintenance')
                ->assertOk()
                ->assertSee('id="app"', false);
            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/user')
                ->assertOk();
            $this->getJson('/api/v1/admin/system-maintenance')
                ->assertOk()
                ->assertJsonPath('data.active', true)
                ->assertJsonPath('data.native_active', false);

            $this->grantAdminStepUp('system.maintenance', $admin);
            $this->postJson('/api/v1/admin/system-maintenance/disable')
                ->assertOk()
                ->assertJsonPath('data.active', false);

            $this->assertFalse($maintenanceMode->active());
            $this->assertSame(false, app(MaintenanceScopeStore::class)->read()['scopes']['public_website']);
            $this->assertSame(false, app(MaintenanceScopeStore::class)->read()['scopes']['authentication']);
            $this->assertSame(false, app(MaintenanceScopeStore::class)->read()['scopes']['dashboard_workspace']);
            $this->get('/')->assertOk();
        } finally {
            if ($maintenanceMode->active()) {
                Artisan::call('planora:maintenance-up');
            }
        }
    }

    public function test_invalid_enable_requests_and_non_admin_changes_are_rejected_without_enabling_maintenance(): void
    {
        $admin = $this->account('admin', 505);
        $this->actingAs($admin, 'sanctum');
        $this->grantAdminStepUp('system.maintenance', $admin);
        $this->postJson('/api/v1/admin/system-maintenance/enable', [
                'message' => '',
                'retry_after' => 2,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'retry_after']);

        $user = $this->account('user', 506);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/admin/system-maintenance/enable', [
                'message' => 'Should not enable',
                'retry_after' => 60,
            ])->assertForbidden();

        $this->assertFalse(app()->maintenanceMode()->active());
    }

    public function test_scope_updates_are_independent_and_reject_unauthorized_accounts(): void
    {
        $admin = $this->account('admin', 510);
        $moderator = $this->account('moderator', 511);
        $user = $this->account('user', 512);

        $this->actingAs($admin, 'sanctum');
        $this->grantAdminStepUp('system.maintenance', $admin);
        $this->patchJson('/api/v1/admin/system-maintenance/scopes/public-website', [
                'enabled' => true,
                'message' => 'The public site is being updated.',
                'retry_after' => 45,
            ])->assertOk()
            ->assertJsonPath('data.scopes.public_website', true)
            ->assertJsonPath('data.scopes.authentication', false)
            ->assertJsonPath('data.scopes.dashboard_workspace', false)
            ->assertJsonPath('data.overall', 'partially_under_maintenance');

        $this->grantAdminStepUp('system.maintenance', $admin);
        $this->patchJson('/api/v1/admin/system-maintenance/preferences', [
            'message' => 'A new public maintenance notice.',
            'estimated_at' => null,
            'retry_after' => 75,
        ])->assertOk()
            ->assertJsonPath('data.message', 'A new public maintenance notice.')
            ->assertJsonPath('data.retry_after', 75)
            ->assertJsonPath('data.scopes.public_website', true)
            ->assertJsonPath('data.scopes.authentication', false)
            ->assertJsonPath('data.scopes.dashboard_workspace', false);

        $this->grantAdminStepUp('system.maintenance', $admin);
        $this->patchJson('/api/v1/admin/system-maintenance/scopes/authentication', [
            'enabled' => true,
            'message' => 'The authentication service is being updated.',
        ])->assertOk()
            ->assertJsonPath('data.scopes.public_website', true)
            ->assertJsonPath('data.scopes.authentication', true)
            ->assertJsonPath('data.scopes.dashboard_workspace', false);

        $this->grantAdminStepUp('system.maintenance', $admin);
        $this->patchJson('/api/v1/admin/system-maintenance/scopes/public-website', [
            'enabled' => false,
        ])->assertOk()
            ->assertJsonPath('data.scopes.public_website', false)
            ->assertJsonPath('data.scopes.authentication', true)
            ->assertJsonPath('data.scopes.dashboard_workspace', false);

        $this->actingAs($moderator, 'sanctum')
            ->patchJson('/api/v1/admin/system-maintenance/scopes/dashboard-workspace', ['enabled' => true])
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/admin/system-maintenance/scopes/dashboard-workspace', ['enabled' => true])
            ->assertForbidden();
        $this->patchJson('/api/v1/admin/system-maintenance/preferences', [
            'message' => 'Unauthorized visitor message change.',
            'retry_after' => 60,
        ])->assertForbidden();
        $this->getJson('/api/v1/admin/system-maintenance/scopes/not-a-scope')->assertNotFound();

        $this->artisan('planora:maintenance-status')
            ->expectsOutputToContain('Planora: Partially under maintenance.')
            ->expectsOutputToContain('Authentication: active')
            ->assertExitCode(0);

        Artisan::call('planora:maintenance-up');
        $this->assertSame([
            'public_website' => false,
            'authentication' => false,
            'dashboard_workspace' => false,
        ], app(MaintenanceScopeStore::class)->read()['scopes']);
    }

    public function test_all_eight_scope_combinations_only_block_the_selected_areas(): void
    {
        $store = app(MaintenanceScopeStore::class);
        $scopes = MaintenanceScopeStore::SCOPES;
        $admin = $this->account('admin', 513);
        $moderator = $this->account('moderator', 514);
        $moderator->moderator_permissions = ['users.view'];
        $user = $this->account('user', 515);

        $dashboard = \Mockery::mock(DashboardDataService::class);
        $dashboard->shouldReceive('for')->andReturn([
            'sections' => ['users' => false, 'activity' => false, 'categories' => false, 'projects' => false, 'tasks' => false, 'reports' => false],
        ]);
        app()->instance(DashboardDataService::class, $dashboard);

        try {
            for ($combination = 0; $combination < 8; $combination++) {
                $store->disableAll();
                foreach ($scopes as $index => $scope) {
                    if (($combination & (1 << $index)) !== 0) {
                        $store->set($scope, true, [
                            'message' => 'The selected Planora area is under maintenance.',
                            'estimated_at' => null,
                            'estimated_at_iso' => null,
                            'retry_after' => 60,
                        ]);
                    }
                }

                $publicExpected = ($combination & 1) !== 0 ? 503 : 200;
                // Public maintenance also closes the regular login and registration surface.
                $authExpected = ($combination & 3) !== 0 ? 503 : 200;
                $workspaceEnabled = ($combination & 4) !== 0;
                $workspaceExpected = $workspaceEnabled ? 503 : 200;

                $this->get('/')->assertStatus($publicExpected);
                $this->get('/terms-and-conditions')->assertStatus($publicExpected);
                $this->get('/privacy-policy')->assertStatus($publicExpected);
                $this->get('/login')->assertStatus($authExpected);
                $this->get('/register')->assertStatus($authExpected);
                foreach ([
                    '/api/v1/auth/login', '/api/v1/auth/register', '/api/v1/auth/register/verify',
                    '/api/v1/auth/register/resend', '/api/v1/auth/password/forgot',
                    '/api/v1/auth/password/resend', '/api/v1/auth/password/verify',
                    '/api/v1/auth/password/reset',
                ] as $authEndpoint) {
                    $response = $this->postJson($authEndpoint, []);
                    if ($authExpected === 503) $response->assertStatus(503);
                    else $this->assertNotSame(503, $response->status(), $authEndpoint.' should remain available when its scope is disabled.');
                }
                $this->app['auth']->forgetGuards();
                auth()->shouldUse('web');
                // Authentication runs before the workspace gate so guests reach login, while authenticated non-admins receive 503.
                $this->get('/dashboard')->assertStatus(302);
                $this->getJson('/api/v1/user')->assertUnauthorized();
                $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();

                $this->withSession(['password_hash_web' => $admin->getAuthPassword()])
                    ->actingAs($admin, 'web')
                    ->get('/admin/dashboard')
                    ->assertOk();
                $this->actingAs($admin, 'sanctum')
                    ->getJson('/api/v1/admin/dashboard')
                    ->assertOk();
                $this->getJson('/api/v1/user')->assertOk();
                $this->actingAs($admin, 'web')->get('/admin/system-maintenance')->assertOk();

                $this->withSession(['password_hash_web' => $moderator->getAuthPassword()])
                    ->actingAs($moderator, 'web')
                    ->get('/admin/dashboard')
                    ->assertStatus($workspaceExpected);
                $this->actingAs($moderator, 'sanctum')
                    ->getJson('/api/v1/admin/dashboard')
                    ->assertStatus($workspaceEnabled ? 503 : 200);

                $this->withSession(['password_hash_web' => $user->getAuthPassword()])
                    ->actingAs($user, 'web')
                    ->get('/dashboard')
                    ->assertStatus($workspaceExpected);
                $this->withSession(['password_hash_web' => $user->getAuthPassword()])
                    ->actingAs($user, 'web')
                    ->get('/account/settings')
                    ->assertStatus($workspaceExpected);
                $this->withSession(['password_hash_web' => $user->getAuthPassword()])
                    ->actingAs($user, 'web')
                    ->get('/projects')
                    ->assertStatus($workspaceExpected);
                $this->withSession(['password_hash_web' => $user->getAuthPassword()])
                    ->actingAs($user, 'web')
                    ->get('/tasks/create')
                    ->assertStatus($workspaceExpected);
                $this->actingAs($user, 'sanctum')
                    ->getJson('/api/v1/user')
                    ->assertStatus($workspaceEnabled ? 503 : 200);
                if ($workspaceEnabled) {
                    $this->actingAs($user, 'sanctum')->getJson('/api/v1/projects')->assertStatus(503);
                    $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks')->assertStatus(503);
                    $this->actingAs($moderator, 'sanctum')->getJson('/api/v1/admin/projects')->assertStatus(503);
                    $this->actingAs($moderator, 'sanctum')->getJson('/api/v1/admin/tasks')->assertStatus(503);
                }
            }

            $this->assertSame(['public_website' => false, 'authentication' => false, 'dashboard_workspace' => false], $store->disableAll()['scopes']);
            $this->get('/sanctum/csrf-cookie')->assertSuccessful();
        } finally {
            $store->disableAll();
        }
    }

    public function test_only_authenticated_admins_can_use_dashboard_routes_during_maintenance(): void
    {
        $maintenanceMode = app()->maintenanceMode();
        $this->assertFalse($maintenanceMode->active(), 'The feature test must not start while maintenance is active.');

        $dashboard = \Mockery::mock(DashboardDataService::class);
        $dashboard->shouldReceive('for')->andReturn([
            'sections' => ['users' => false, 'activity' => false, 'categories' => false, 'projects' => false, 'tasks' => false, 'reports' => false],
        ]);
        app()->instance(DashboardDataService::class, $dashboard);

        $admin = $this->account('admin', 507);
        $moderator = $this->account('moderator', 508);
        $moderator->moderator_permissions = ['users.view'];
        $user = $this->account('user', 509);

        $maintenanceMode->activate([
            'planora' => [
                'message' => 'Planora is temporarily unavailable.',
                'estimated_at' => null,
                'retry_after' => 60,
            ],
            'retry' => 60,
        ]);

        try {
            $this->actingAs($admin, 'web')
                ->get('/admin/dashboard')
                ->assertOk()
                ->assertSee('id="app"', false);

            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/v1/admin/dashboard')
                ->assertOk()
                ->assertJsonPath('data.sections.users', false);

            $this->actingAs($admin, 'web')->get('/admin/system-maintenance')->assertOk();
            $this->getJson('/api/v1/admin/system-maintenance')
                ->assertOk()
                ->assertJsonPath('data.active', true);

            $this->withSession(['password_hash_web' => $moderator->getAuthPassword()])
                ->actingAs($moderator, 'web')
                ->get('/admin/dashboard')
                ->assertStatus(503)
                ->assertSee('We will be back soon');
            $this->actingAs($moderator, 'sanctum')
                ->getJson('/api/v1/admin/dashboard')
                ->assertStatus(503);
            $this->getJson('/api/v1/admin/system-maintenance')->assertForbidden();
            $this->postJson('/api/v1/admin/system-maintenance/disable')->assertForbidden();

            $this->withSession(['password_hash_web' => $user->getAuthPassword()])
                ->actingAs($user, 'web')
                ->get('/admin/dashboard')
                ->assertStatus(503)
                ->assertSee('We will be back soon');
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/admin/dashboard')
                ->assertStatus(503);
            $this->getJson('/api/v1/user')->assertStatus(503);

            $this->flushSession();
            $this->app['auth']->forgetGuards();
            $this->get('/admin/dashboard')
                ->assertStatus(503)
                ->assertSee('We will be back soon');
            $this->getJson('/api/v1/admin/dashboard')->assertStatus(503);

            $this->grantAdminStepUp('system.maintenance', $admin);
            $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/system-maintenance/disable')
                ->assertOk()
                ->assertJsonPath('data.active', false);

            $this->assertFalse($maintenanceMode->active());
            $this->actingAs($admin, 'web')->get('/admin/dashboard')->assertOk();
            $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/dashboard')->assertOk();
            $this->withSession(['password_hash_web' => $moderator->getAuthPassword()])
                ->actingAs($moderator, 'web')->get('/admin/dashboard')->assertOk();
            $this->actingAs($moderator, 'sanctum')->getJson('/api/v1/admin/dashboard')->assertOk();
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/dashboard')->assertForbidden();
            $this->getJson('/api/v1/admin/system-maintenance')->assertForbidden();
        } finally {
            if ($maintenanceMode->active()) {
                Artisan::call('planora:maintenance-up');
            }
        }
    }

    private function account(string $role, int $id): User
    {
        return User::factory()->create([
            'name' => ucfirst($role).' QA',
            'password' => Hash::make('maintenance-test-password'),
            'role' => $role,
            'status' => 'active',
            'moderator_permissions' => [],
        ]);
    }
}
