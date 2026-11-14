<?php

namespace Tests\Feature\Admin\RolesPermissions;

use App\Models\Project;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_roles_permissions_page_is_available_to_admin_only(): void
    {
        $admin = $this->account('admin');
        $moderator = $this->account('moderator', ['users.view']);
        $user = $this->account('user');

        $this->actingAs($admin)->get('/admin/roles-permissions')->assertOk();
        $this->actingAs($moderator)->get('/admin/roles-permissions')->assertRedirect('/admin/users');
        $this->actingAs($user)->get('/admin/roles-permissions')->assertRedirect('/dashboard');
    }

    private function account(string $role, array $permissions = []): User
    {
        return User::factory()->create([
            'role' => $role, 'status' => 'active',
            'moderator_permissions' => $role === 'moderator' ? $permissions : null,
        ]);
    }

    public function test_role_permission_management_is_admin_only_and_returns_catalog_and_moderators(): void
    {
        $admin = $this->account('admin');
        $moderator = $this->account('moderator', ['users.view']);
        $this->account('user');

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/roles-permissions')
            ->assertOk()->assertJsonFragment(['module' => 'Users'])
            ->assertJsonFragment(['sensitive' => true])
            ->assertJsonFragment(['categories.view'])
            ->assertJsonFragment(['projects.status.manage'])
            ->assertJsonFragment(['tasks.status.manage'])
            ->assertJsonFragment(['reports.view']);
        $this->getJson('/api/v1/admin/roles-permissions/moderators?search='.$moderator->name)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $moderator->id)
            ->assertJsonPath('meta.role_counts.admin', 1)->assertJsonPath('meta.role_counts.moderator', 1);

        $limitedModerator = $this->account('moderator', ['users.view']);
        $this->actingAs($limitedModerator, 'sanctum')->getJson('/api/v1/admin/roles-permissions')->assertForbidden();
        $user = $this->account('user');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/roles-permissions/moderators')->assertForbidden();
    }

    public function test_admin_can_assign_and_revoke_only_catalogued_moderator_permissions_with_audit(): void
    {
        $admin = $this->account('admin');
        $moderator = $this->account('moderator', ['users.view']);
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/v1/admin/roles-permissions/moderators/'.$moderator->id, ['permissions' => ['users.view', 'activity.view']])
            ->assertOk()->assertJsonPath('user.moderator_permissions.1', 'activity.view');
        $this->assertDatabaseHas('user_activity_logs', ['event' => 'moderator.permissions_changed', 'actor_id' => $admin->id, 'user_id' => $moderator->id]);
        $moderator->refresh();
        $this->assertTrue($moderator->hasManagementPermission('activity.view'));

        $this->putJson('/api/v1/admin/roles-permissions/moderators/'.$moderator->id, ['permissions' => []])->assertOk();
        $this->assertFalse($moderator->fresh()->hasManagementPermission('activity.view'));
        $this->putJson('/api/v1/admin/roles-permissions/moderators/'.$moderator->id, ['permissions' => ['roles.manage']])->assertUnprocessable();
        $user = $this->account('user');
        $this->putJson('/api/v1/admin/roles-permissions/moderators/'.$user->id, ['permissions' => ['users.view']])->assertForbidden();
    }

    public function test_dashboard_returns_operational_aggregates_to_admin_and_only_granted_moderator_sections(): void
    {
        $admin = $this->account('admin');
        $owner = $this->account('user');
        Project::create(['name' => 'Current project', 'status' => 'active', 'owner_id' => $owner->id]);
        Task::create(['title' => 'Past due task', 'status' => 'pending', 'priority' => 'high', 'due_date' => today()->subDay()->toDateString(), 'owner_id' => $owner->id, 'created_by' => $admin->id]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/dashboard')
            ->assertOk()->assertJsonPath('data.sections.projects', true)
            ->assertJsonPath('data.projects.active', 1)->assertJsonPath('data.tasks.overdue', 1);

        $userPermissionModerator = $this->account('moderator', ['users.view']);
        $this->actingAs($userPermissionModerator, 'sanctum')->getJson('/api/v1/admin/dashboard')
            ->assertOk()->assertJsonPath('data.sections.users', true)->assertJsonPath('data.sections.activity', false)
            ->assertJsonMissingPath('data.projects')->assertJsonMissingPath('data.tasks');

        $activityPermissionModerator = $this->account('moderator', ['activity.view']);
        $this->actingAs($activityPermissionModerator, 'sanctum')->getJson('/api/v1/admin/dashboard')
            ->assertOk()->assertJsonPath('data.sections.users', false)->assertJsonPath('data.sections.activity', true)
            ->assertJsonMissingPath('data.users')->assertJsonMissingPath('data.projects');

        foreach (['categories', 'projects', 'tasks', 'reports'] as $module) {
            $moduleModerator = $this->account('moderator', ["{$module}.view"]);
            $response = $this->actingAs($moduleModerator, 'sanctum')->getJson('/api/v1/admin/dashboard')
                ->assertOk()->assertJsonPath("data.sections.{$module}", true);
            foreach (['users', 'activity', 'categories', 'projects', 'tasks', 'reports'] as $otherModule) {
                if ($otherModule !== $module) $response->assertJsonPath("data.sections.{$otherModule}", false);
            }
        }

        $deniedModerator = $this->account('moderator');
        $this->actingAs($deniedModerator, 'sanctum')->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->assertGreaterThanOrEqual(0, UserActivityLog::query()->count());
    }

    public function test_expanded_module_permissions_allow_only_their_explicit_read_and_status_capabilities(): void
    {
        $admin = $this->account('admin');
        $owner = $this->account('user');
        $category = Category::create(['name' => 'Permission scope category']);
        $project = Project::create(['name' => 'Permission scope project', 'status' => 'planned', 'owner_id' => $owner->id]);
        $task = Task::create(['title' => 'Permission scope task', 'status' => 'pending', 'priority' => 'medium', 'owner_id' => $owner->id, 'created_by' => $admin->id, 'project_id' => $project->id]);

        $moderator = $this->account('moderator', [
            'categories.view', 'projects.view', 'projects.status.manage', 'tasks.view', 'tasks.status.manage', 'reports.view',
        ]);
        $this->actingAs($moderator, 'sanctum');

        $this->getJson('/api/v1/admin/categories')->assertOk();
        $this->getJson('/api/v1/admin/categories/'.$category->id)->assertOk();
        $this->postJson('/api/v1/admin/categories', ['name' => 'Moderator category'])->assertForbidden();
        $this->getJson('/api/v1/admin/projects')->assertOk();
        $this->getJson('/api/v1/admin/projects/'.$project->id)->assertOk();
        $this->patchJson('/api/v1/admin/projects/'.$project->id.'/status', ['status' => 'active'])->assertOk()->assertJsonPath('project.status', 'active');
        $this->patchJson('/api/v1/admin/projects/'.$project->id, ['name' => 'Changed by moderator'])->assertForbidden();
        $this->getJson('/api/v1/admin/tasks')->assertOk();
        $this->getJson('/api/v1/admin/tasks/'.$task->id)->assertOk();
        $this->patchJson('/api/v1/admin/tasks/'.$task->id.'/status', ['status' => 'in_progress'])->assertOk()->assertJsonPath('task.status', 'in_progress');
        $this->patchJson('/api/v1/admin/tasks/'.$task->id, ['title' => 'Changed by moderator'])->assertForbidden();
        $this->getJson('/api/v1/admin/reports')->assertOk();
        $this->getJson('/api/v1/admin/reports/export')->assertForbidden();

        $limited = $this->account('moderator', ['projects.view', 'tasks.view']);
        $this->actingAs($limited, 'sanctum')
            ->patchJson('/api/v1/admin/projects/'.$project->id.'/status', ['status' => 'completed'])->assertForbidden();
        $this->patchJson('/api/v1/admin/tasks/'.$task->id.'/status', ['status' => 'completed'])->assertForbidden();
        $statusOnly = $this->account('moderator', ['projects.status.manage']);
        $this->actingAs($statusOnly, 'sanctum')->patchJson('/api/v1/admin/projects/'.$project->id.'/status', ['status' => 'active'])->assertForbidden();

        $user = $this->account('user');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/categories')->assertForbidden();
        $this->getJson('/api/v1/admin/projects')->assertForbidden();
        $this->getJson('/api/v1/admin/tasks')->assertForbidden();
        $this->getJson('/api/v1/admin/reports')->assertForbidden();

        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/projects/'.$project->id.'/status', ['status' => 'completed'])->assertOk();
        $this->patchJson('/api/v1/admin/tasks/'.$task->id.'/status', ['status' => 'completed'])->assertOk();
    }

    public function test_permission_assignment_rejects_duplicates_and_missing_module_dependencies(): void
    {
        $admin = $this->account('admin');
        $moderator = $this->account('moderator');
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/v1/admin/roles-permissions/moderators/'.$moderator->id, ['permissions' => ['projects.status.manage']])
            ->assertUnprocessable()->assertJsonValidationErrors(['permissions']);
        $this->putJson('/api/v1/admin/roles-permissions/moderators/'.$moderator->id, ['permissions' => ['users.view', 'users.view']])
            ->assertUnprocessable()->assertJsonValidationErrors(['permissions.1']);
        $this->putJson('/api/v1/admin/roles-permissions/moderators/'.$moderator->id, ['permissions' => ['users.status.manage']])
            ->assertUnprocessable()->assertJsonValidationErrors(['permissions']);
    }
}
