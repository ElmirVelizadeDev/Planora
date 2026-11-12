<?php

namespace Tests\Feature\Admin\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminTaskManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('tasks'), 'Run the task migration before the feature tests.');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_task_endpoints_require_an_active_admin(): void
    {
        $this->getJson('/api/v1/admin/tasks')->assertUnauthorized();

        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/tasks')->assertForbidden();
    }

    public function test_admin_can_list_search_filter_paginate_and_read_database_statistics(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $assigned = User::factory()->create();
        Task::create(['title' => 'Prepare release', 'description' => 'Release checklist', 'status' => 'in_progress', 'priority' => 'high', 'due_date' => now()->addDay()->toDateString(), 'owner_id' => $owner->id, 'assigned_user_id' => $assigned->id, 'created_by' => $admin->id]);
        Task::create(['title' => 'Archive notes', 'status' => 'pending', 'priority' => 'low', 'owner_id' => $owner->id, 'created_by' => $admin->id]);
        Task::create(['title' => 'Old overdue task', 'status' => 'pending', 'priority' => 'urgent', 'due_date' => now()->subDay()->toDateString(), 'owner_id' => $owner->id, 'created_by' => $admin->id]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/tasks?search=release&status=in_progress&priority=high&assigned_user_id='.$assigned->id.'&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.statistics.total', 3)->assertJsonPath('meta.statistics.pending', 2)
            ->assertJsonPath('meta.statistics.in_progress', 1)->assertJsonPath('meta.statistics.overdue', 1)
            ->assertJsonPath('data.0.title', 'Prepare release')
            ->assertJsonPath('data.0.owner.id', $owner->id)
            ->assertJsonPath('data.0.assigned_user.id', $assigned->id);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/tasks?per_page=1&page=2&sort=oldest')
            ->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('meta.current_page', 2)->assertJsonCount(1, 'data');
    }

    public function test_admin_task_date_range_filters_due_date_inclusively_and_validates_order(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        foreach (['2026-04-10', '2026-04-11', '2026-04-12', '2026-04-13'] as $index => $date) {
            Task::create(['title' => 'Date task '.$index, 'due_date' => $date, 'owner_id' => $owner->id, 'created_by' => $admin->id]);
        }
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/v1/admin/tasks')->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson('/api/v1/admin/tasks?start_date=2026-04-11&end_date=2026-04-12')
            ->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/admin/tasks?start_date=2026-04-12')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/admin/tasks?end_date=2026-04-11')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/admin/tasks?start_date=2026-04-12&end_date=2026-04-12')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/admin/tasks?start_date=2026-04-13&end_date=2026-04-12')->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
        $this->getJson('/api/v1/admin/tasks?start_date=04/12/2026')->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
    }

    public function test_admin_can_create_update_show_and_soft_delete_tasks(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $assignee = User::factory()->create();
        $this->actingAs($admin, 'sanctum');

        $created = $this->postJson('/api/v1/admin/tasks', [
            'title' => 'New task', 'description' => 'Work description', 'status' => 'pending', 'priority' => 'medium',
            'due_date' => now()->addWeek()->toDateString(), 'owner_id' => $owner->id, 'assigned_user_id' => $assignee->id,
        ])->assertCreated()->assertJsonPath('task.title', 'New task')->assertJsonPath('task.owner.id', $owner->id)->assertJsonPath('task.assigned_user.id', $assignee->id);
        $id = $created->json('task.id');

        $this->patchJson('/api/v1/admin/tasks/'.$id, ['title' => 'Updated task', 'status' => 'completed', 'assigned_user_id' => null])
            ->assertOk()->assertJsonPath('task.title', 'Updated task')->assertJsonPath('task.status', 'completed')->assertJsonPath('task.assigned_user', null);
        $this->getJson('/api/v1/admin/tasks/'.$id)->assertOk()->assertJsonPath('task.owner.id', $owner->id)->assertJsonPath('task.status', 'completed');
        $this->deleteJson('/api/v1/admin/tasks/'.$id)->assertOk();
        $this->assertSoftDeleted('tasks', ['id' => $id]);
        $this->getJson('/api/v1/admin/tasks/'.$id)->assertNotFound();
    }

    public function test_task_writes_validate_user_relationships_and_enum_values(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/tasks', [
            'title' => '', 'status' => 'blocked', 'priority' => 'critical', 'owner_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'status', 'priority', 'owner_id']);
    }
}
