<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function headers(): array
    {
        return ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_initial_schema_has_only_supported_roles_and_statuses(): void
    {
        $role = \DB::selectOne("SHOW COLUMNS FROM users WHERE Field = 'role'")->Type;
        $status = \DB::selectOne("SHOW COLUMNS FROM users WHERE Field = 'status'")->Type;

        $this->assertSame("enum('user','moderator','admin')", $role);
        $this->assertSame("enum('active','inactive','suspended','banned')", $status);
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('user_activity_logs'));
    }

    public function test_guests_and_regular_users_cannot_access_user_management_apis(): void
    {
        $this->withHeaders($this->headers())->getJson('/api/v1/admin/users')->assertUnauthorized();

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAs($user, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id.'/role', ['role' => 'admin'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id.'/status', ['status' => 'banned', 'reason' => 'self action'])->assertForbidden();
    }

    public function test_moderator_permissions_are_enforced_per_account(): void
    {
        $target = User::factory()->create(['role' => 'user']);
        $moderator = User::factory()->create([
            'role' => 'moderator',
            'moderator_permissions' => ['users.view', 'activity.view'],
        ]);

        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users')->assertOk();
        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$target->id.'/role', ['role' => 'admin'])->assertForbidden();
        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$target->id.'/status', ['status' => 'banned', 'reason' => 'unauthorized'])->assertForbidden();

        $restricted = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => []]);
        $this->actingAs($restricted, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_admin_can_create_users_with_hashed_password_and_role_and_status_separated(): void
    {
        $admin = $this->admin();
        $email = 'managed-'.Str::uuid().'@example.test';

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users', [
                'name' => 'Managed Account',
                'email' => $email,
                'password' => 'Initial-Password-123',
                'password_confirmation' => 'Initial-Password-123',
                'role' => 'user',
                'status' => 'inactive',
            ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.status', 'inactive')
            ->assertJsonMissingPath('user.password');

        $created = User::where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check('Initial-Password-123', $created->password));
        $this->assertSame($admin->id, $created->status_changed_by);
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $created->id, 'actor_id' => $admin->id, 'event' => 'user.created']);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users', [
                'name' => 'Unsafe Account', 'email' => 'unsafe-'.Str::uuid().'@example.test',
                'password' => 'Initial-Password-123', 'password_confirmation' => 'Initial-Password-123',
                'role' => 'seller', 'status' => 'active',
            ])->assertUnprocessable();
    }

    public function test_search_filters_sorting_and_pagination_use_database_records(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Unique Search Person', 'email' => 'search-person@example.test', 'role' => 'user', 'status' => 'active']);
        User::factory()->create(['name' => 'Other Name', 'email' => 'other@example.test', 'role' => 'moderator', 'status' => 'suspended']);

        $response = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users?search=search-person&role=user&status=active&verification=verified&sort=name_asc&per_page=10');
        $response->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Unique Search Person');
        $this->assertArrayNotHasKey('password', $response->json('data.0'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.0'));

        User::factory()->count(12)->create(['role' => 'user']);
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users?role=user&per_page=10&page=2')
            ->assertOk()->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.per_page', 10);
    }

    public function test_admin_role_status_and_moderator_permission_changes_are_audited(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'user']);
        $base = '/api/v1/admin/users/'.$target->id;

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/role', ['role' => 'moderator', 'moderator_permissions' => ['users.view', 'activity.view']])
            ->assertOk()->assertJsonPath('user.role', 'moderator');
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $target->id, 'actor_id' => $admin->id, 'event' => 'user.role_changed']);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->putJson($base.'/permissions', ['permissions' => ['users.view', 'activity.view', 'users.status.manage']])
            ->assertOk()->assertJsonPath('user.moderator_permissions.2', 'users.status.manage');

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/status', ['status' => 'suspended', 'reason' => 'Repeated policy violations'])
            ->assertOk()->assertJsonPath('user.status', 'suspended')->assertJsonPath('user.status_reason', 'Repeated policy violations');

        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $target->id, 'actor_id' => $admin->id, 'event' => 'account.suspended']);
    }

    public function test_moderator_may_only_use_explicitly_assigned_status_permission(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'user']);
        $moderator = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => ['users.view', 'users.status.manage']]);

        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$target->id.'/status', ['status' => 'suspended', 'reason' => 'Needs review'])
            ->assertOk()->assertJsonPath('user.status', 'suspended');

        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$admin->id.'/status', ['status' => 'banned', 'reason' => 'attempt'])
            ->assertForbidden();
    }

    public function test_suspended_and_banned_accounts_are_blocked_and_restorable(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user', 'password' => Hash::make('Member-Password-123')]);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id.'/status', ['status' => 'banned', 'reason' => 'Confirmed abuse'])
            ->assertOk();

        $this->actingAs($user->fresh(), 'web')->withHeaders($this->headers())
            ->getJson('/api/v1/user')->assertUnauthorized();

        $this->withHeaders($this->headers())->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Member-Password-123',
        ])->assertForbidden();

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id.'/status', ['status' => 'active'])
            ->assertOk()->assertJsonPath('user.status', 'active');
    }

    public function test_last_active_admin_cannot_be_demoted_suspended_banned_or_deleted(): void
    {
        $admin = $this->admin();
        $base = '/api/v1/admin/users/'.$admin->id;

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/role', ['role' => 'user'])->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/status', ['status' => 'suspended', 'reason' => 'test reason'])->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->deleteJson($base)->assertUnprocessable();
    }

    public function test_soft_deletion_is_audited_and_recoverable(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->deleteJson('/api/v1/admin/users/'.$user->id)->assertOk();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $user->id, 'event' => 'user.deleted']);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users/'.$user->id.'/restore')->assertOk()->assertJsonPath('user.status', 'active');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_activity_and_roles_endpoints_return_only_recorded_data_and_permissions(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user']);
        UserActivityLog::create(['user_id' => $user->id, 'actor_id' => $admin->id, 'event' => 'profile.updated', 'description' => 'User profile updated.', 'created_at' => now()]);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users/activity?user_id='.$user->id)
            ->assertOk()->assertJsonPath('data.0.event', 'profile.updated');
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users/roles-permissions')
            ->assertOk()->assertJsonPath('roles.moderator', 'Limited user visibility and only explicitly assigned moderation actions.');
    }
}
