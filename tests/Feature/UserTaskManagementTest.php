<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserTaskManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('tasks'), 'Run the task migration before the feature tests.');
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'status' => 'active']);
    }

    public function test_task_endpoints_require_an_authenticated_user(): void
    {
        $this->getJson('/api/v1/tasks')->assertUnauthorized();
        $this->postJson('/api/v1/tasks', ['title' => 'Unauthenticated'])->assertUnauthorized();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/tasks')->assertForbidden();
    }

    public function test_user_lists_only_owned_and_assigned_tasks_and_cannot_access_another_users_task(): void
    {
        $user = $this->user();
        $other = $this->user();
        $owned = Task::create(['title' => 'Write release notes', 'description' => 'Release notes text', 'status' => 'pending', 'priority' => 'high', 'owner_id' => $user->id, 'created_by' => $user->id]);
        $assigned = Task::create(['title' => 'Review release notes', 'status' => 'in_progress', 'priority' => 'medium', 'owner_id' => $other->id, 'assigned_user_id' => $user->id, 'created_by' => $other->id]);
        $private = Task::create(['title' => 'Private other task', 'owner_id' => $other->id, 'created_by' => $other->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?search=release&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'title', 'description', 'status', 'priority', 'due_date', 'is_owned', 'created_at', 'updated_at']], 'meta' => ['current_page', 'last_page', 'per_page', 'total', 'statistics' => ['total', 'pending', 'in_progress', 'completed', 'overdue', 'upcoming']]])
            ->assertJsonPath('meta.statistics.total', 2)->assertJsonPath('meta.statistics.pending', 1)
            ->assertJsonPath('meta.statistics.in_progress', 1);
        $this->getJson('/api/v1/tasks/'.$owned->id)->assertOk()->assertJsonPath('task.title', $owned->title);
        $this->getJson('/api/v1/tasks/'.$assigned->id)->assertOk()->assertJsonPath('task.is_owned', false);
        $this->patchJson('/api/v1/tasks/'.$assigned->id.'/status', ['status' => 'in_progress'])->assertOk()->assertJsonPath('task.status', 'in_progress');
        $this->patchJson('/api/v1/tasks/'.$assigned->id, ['title' => 'Owner only'])->assertNotFound();
        $this->deleteJson('/api/v1/tasks/'.$assigned->id)->assertNotFound();
        $this->getJson('/api/v1/tasks/'.$private->id)->assertNotFound();
    }

    public function test_user_can_create_update_complete_and_soft_delete_only_owned_tasks(): void
    {
        $user = $this->user();
        $other = $this->user();
        $this->actingAs($user, 'sanctum');

        $created = $this->postJson('/api/v1/tasks', [
            'title' => 'Plan launch', 'description' => 'Prepare checklist', 'status' => 'pending', 'priority' => 'urgent',
            'due_date' => now()->addWeek()->toDateString(), 'owner_id' => $other->id, 'created_by' => $other->id, 'assigned_user_id' => $other->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['owner_id', 'created_by', 'assigned_user_id']);

        $created = $this->postJson('/api/v1/tasks', [
            'title' => 'Plan launch', 'description' => 'Prepare checklist', 'status' => 'pending', 'priority' => 'urgent',
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertCreated()->assertJsonPath('task.title', 'Plan launch')->assertJsonPath('task.is_owned', true);
        $id = $created->json('task.id');
        $this->assertDatabaseHas('tasks', ['id' => $id, 'owner_id' => $user->id, 'created_by' => $user->id, 'assigned_user_id' => null]);

        $this->patchJson('/api/v1/tasks/'.$id, ['title' => 'Launch plan revised', 'status' => 'in_progress'])->assertOk()->assertJsonPath('task.title', 'Launch plan revised');
        $this->patchJson('/api/v1/tasks/'.$id.'/status', ['status' => 'completed'])->assertOk()->assertJsonPath('task.status', 'completed');

        $otherTask = Task::create(['title' => 'Other task', 'owner_id' => $other->id, 'created_by' => $other->id]);
        $this->patchJson('/api/v1/tasks/'.$otherTask->id, ['title' => 'Intrusion'])->assertNotFound();
        $this->deleteJson('/api/v1/tasks/'.$otherTask->id)->assertNotFound();
        $this->deleteJson('/api/v1/tasks/'.$id)->assertOk();
        $this->assertSoftDeleted('tasks', ['id' => $id]);
    }

    public function test_user_task_search_filters_dates_validation_and_pagination_work_together(): void
    {
        $user = $this->user();
        Task::create(['title' => 'Today high priority', 'description' => 'Find this entry', 'status' => 'pending', 'priority' => 'high', 'due_date' => now()->toDateString(), 'owner_id' => $user->id, 'created_by' => $user->id]);
        Task::create(['title' => 'Future low priority', 'status' => 'pending', 'priority' => 'low', 'due_date' => now()->addDays(3)->toDateString(), 'owner_id' => $user->id, 'created_by' => $user->id]);
        Task::create(['title' => 'Completed today', 'status' => 'completed', 'priority' => 'high', 'due_date' => now()->toDateString(), 'owner_id' => $user->id, 'created_by' => $user->id]);
        Task::create(['title' => 'Past due task', 'status' => 'pending', 'priority' => 'urgent', 'due_date' => now()->subDay()->toDateString(), 'owner_id' => $user->id, 'created_by' => $user->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?search=find&status=pending&priority=high&due=today&per_page=1&page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)->assertJsonPath('data.0.title', 'Today high priority')
            ->assertJsonPath('meta.statistics.total', 4)->assertJsonPath('meta.statistics.completed', 1)
            ->assertJsonPath('meta.statistics.overdue', 1)->assertJsonPath('meta.statistics.upcoming', 1);
        $this->getJson('/api/v1/tasks?per_page=1&page=2')->assertOk()->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.last_page', 4)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tasks?due=upcoming')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Future low priority');
        $this->getJson('/api/v1/tasks?due=overdue')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Past due task');
        $this->getJson('/api/v1/tasks?sort=priority_desc')->assertOk()
            ->assertJsonPath('data.0.title', 'Past due task');
        $this->getJson('/api/v1/tasks?search=not-a-real-task')->assertOk()
            ->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/tasks?status=not-a-status')->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->postJson('/api/v1/tasks', ['title' => '', 'owner_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors(['title', 'owner_id']);
    }
}
