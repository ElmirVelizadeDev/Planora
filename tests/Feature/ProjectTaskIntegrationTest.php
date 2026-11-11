<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectTaskIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasColumn('tasks', 'project_id'), 'Run the project-task relationship migration before the feature tests.');
    }

    private function user(): User { return User::factory()->create(['role' => 'user', 'status' => 'active']); }
    private function admin(): User { return User::factory()->create(['role' => 'admin', 'status' => 'active']); }

    public function test_users_can_only_list_and_view_their_assigned_projects(): void
    {
        $user = $this->user(); $other = $this->user();
        $mine = Project::create(['name' => 'My project', 'status' => 'active', 'owner_id' => $user->id]);
        $private = Project::create(['name' => 'Private project', 'status' => 'planned', 'owner_id' => $other->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/projects')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/v1/projects?status=active')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/v1/projects?status=completed')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/projects/'.$mine->id)->assertOk()->assertJsonPath('project.id', $mine->id);
        $this->getJson('/api/v1/projects/'.$private->id)->assertNotFound();
        $this->postJson('/api/v1/projects', ['name' => 'Nope'])->assertStatus(405);
    }

    public function test_project_task_listing_keeps_existing_owner_or_assignee_task_access_scope(): void
    {
        $user = $this->user(); $other = $this->user();
        $project = Project::create(['name' => 'Assigned project', 'status' => 'active', 'owner_id' => $user->id]);
        $visible = Task::create(['title' => 'Assigned project task', 'status' => 'pending', 'priority' => 'high', 'project_id' => $project->id, 'owner_id' => $other->id, 'assigned_user_id' => $user->id, 'created_by' => $other->id]);
        Task::create(['title' => 'Unrelated project task', 'project_id' => $project->id, 'owner_id' => $other->id, 'created_by' => $other->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/projects/'.$project->id.'/tasks')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $visible->id);
        $this->getJson('/api/v1/projects/999999/tasks')->assertNotFound();
    }

    public function test_admin_can_create_project_tasks_and_project_delete_detaches_them(): void
    {
        $admin = $this->admin(); $owner = $this->user();
        $project = Project::create(['name' => 'Admin project', 'status' => 'active', 'owner_id' => $owner->id]);
        $this->actingAs($admin, 'sanctum');
        $created = $this->postJson('/api/v1/admin/projects/'.$project->id.'/tasks', [
            'title' => 'Project delivery task', 'owner_id' => $owner->id, 'assigned_user_id' => $owner->id,
            'status' => 'pending', 'priority' => 'medium', 'project_id' => $project->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['project_id']);
        $created = $this->postJson('/api/v1/admin/projects/'.$project->id.'/tasks', [
            'title' => 'Project delivery task', 'owner_id' => $owner->id, 'assigned_user_id' => $owner->id,
            'status' => 'pending', 'priority' => 'medium',
        ])->assertCreated()->assertJsonPath('task.project_id', $project->id);
        $taskId = $created->json('task.id');
        $this->getJson('/api/v1/admin/projects/'.$project->id.'/tasks?status=pending&priority=medium')->assertOk()->assertJsonPath('meta.total', 1);
        $this->deleteJson('/api/v1/admin/projects/'.$project->id)->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $taskId, 'project_id' => null]);
    }

    public function test_non_users_cannot_be_assigned_as_project_owners(): void
    {
        $admin = $this->admin(); $moderator = User::factory()->create(['role' => 'moderator', 'status' => 'active']);
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/projects', ['name' => 'Bad owner', 'status' => 'planned', 'owner_id' => $moderator->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['owner_id']);
    }
}
