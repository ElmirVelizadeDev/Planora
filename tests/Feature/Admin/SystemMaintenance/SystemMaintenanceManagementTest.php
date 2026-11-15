<?php

namespace Tests\Feature\Admin\SystemMaintenance;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemMaintenanceManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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
            ->assertJsonPath('data.message', config('planora.maintenance.message'))
            ->assertJsonMissingPath('data.secret');
    }

    public function test_admin_can_enable_and_disable_native_maintenance_through_the_panel_while_ordinary_access_is_blocked(): void
    {
        $maintenanceMode = app()->maintenanceMode();
        $this->assertFalse($maintenanceMode->active(), 'The feature test must not start while maintenance is active.');

        $admin = $this->account('admin', 503);
        $user = $this->account('user', 504);

        try {
            $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/system-maintenance/enable', [
                'message' => 'Planora is receiving a scheduled update.',
                'estimated_at' => '2099-01-01T18:00:00+00:00',
                'retry_after' => 75,
            ])->assertOk()
                ->assertJsonPath('data.active', true)
                ->assertJsonPath('data.message', 'Planora is receiving a scheduled update.')
                ->assertJsonPath('data.estimated_at', '2099-01-01T18:00:00+00:00')
                ->assertJsonPath('data.retry_after', 75)
                ->assertJsonMissingPath('data.secret');

            $this->assertTrue($maintenanceMode->active());
            $this->get('/')->assertStatus(503);
            $this->getJson('/api/v1/admin/settings')->assertStatus(503);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/admin/system-maintenance')
                ->assertForbidden();
            $this->postJson('/api/v1/admin/system-maintenance/disable')->assertForbidden();
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
                ->assertJsonPath('data.active', true);

            $this->postJson('/api/v1/admin/system-maintenance/disable')
                ->assertOk()
                ->assertJsonPath('data.active', false);

            $this->assertFalse($maintenanceMode->active());
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
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/system-maintenance/enable', [
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

    private function account(string $role, int $id): User
    {
        $user = new User();
        $user->forceFill([
            'id' => $id,
            'name' => ucfirst($role).' QA',
            'email' => strtolower($role).$id.'@planora.test',
            'password' => Hash::make('maintenance-test-password'),
            'role' => $role,
            'status' => 'active',
            'moderator_permissions' => [],
        ]);

        return $user;
    }
}
