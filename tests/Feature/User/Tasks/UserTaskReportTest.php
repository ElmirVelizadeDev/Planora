<?php

namespace Tests\Feature\User\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserTaskReportTest extends TestCase
{
    use DatabaseTransactions;

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => 'user', 'status' => 'active']);
    }

    private function task(User $owner, array $attributes = []): Task
    {
        $createdAt = $attributes['created_at'] ?? '2026-04-12 12:00:00';
        $attributes['created_at'] = $createdAt;
        $attributes['updated_at'] = $attributes['updated_at'] ?? $createdAt;
        $task = Task::create($attributes + [
            'title' => 'Report task', 'status' => 'pending', 'priority' => 'medium',
            'owner_id' => $owner->id, 'created_by' => $owner->id,
        ]);
        $task->forceFill(['created_at' => $createdAt, 'updated_at' => $attributes['updated_at']])->saveQuietly();

        return $task;
    }

    public function test_report_is_authenticated_role_scoped_and_includes_only_owned_or_assigned_records(): void
    {
        $user = $this->user();
        $other = $this->user();
        $this->task($user, ['title' => 'Owned pending']);
        $this->task($other, ['title' => 'Assigned in progress', 'assigned_user_id' => $user->id, 'status' => 'in_progress', 'priority' => 'high']);
        $this->task($user, ['title' => 'Owned and assigned once', 'assigned_user_id' => $user->id, 'status' => 'completed']);
        $this->task($other, ['title' => 'Private task']);
        $deleted = $this->task($user, ['title' => 'Deleted task']);
        $deleted->delete();

        $this->getJson('/api/v1/tasks/reports?start_date=2026-04-01&end_date=2026-04-30')->assertUnauthorized();
        $admin = $this->user(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/tasks/reports?start_date=2026-04-01&end_date=2026-04-30')->assertForbidden();
        $inactive = $this->user(['status' => 'inactive']);
        $this->actingAs($inactive, 'sanctum')->getJson('/api/v1/tasks/reports?start_date=2026-04-01&end_date=2026-04-30')->assertForbidden();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks/reports?start_date=2026-04-01&end_date=2026-04-30')->assertOk();
        $response->assertJsonPath('data.metrics.total', 3)
            ->assertJsonPath('data.metrics.pending', 1)
            ->assertJsonPath('data.metrics.in_progress', 1)
            ->assertJsonPath('data.metrics.completed', 1)
            ->assertJsonCount(3, 'data.recent_tasks')
            ->assertJsonPath('data.creation_trend.11.date', '2026-04-12')
            ->assertJsonPath('data.creation_trend.11.count', 3);
        $this->assertStringNotContainsString('Private task', $response->getContent());
        $this->assertStringNotContainsString('Deleted task', $response->getContent());
        $this->assertStringNotContainsString('password', strtolower($response->getContent()));
    }

    public function test_report_filters_combine_and_period_bounds_are_inclusive(): void
    {
        $user = $this->user();
        $this->task($user, ['title' => 'Start boundary', 'status' => 'pending', 'priority' => 'high', 'created_at' => '2026-04-10 00:00:00']);
        $this->task($user, ['title' => 'End boundary', 'status' => 'pending', 'priority' => 'high', 'created_at' => '2026-04-12 23:59:59']);
        $this->task($user, ['title' => 'Wrong priority', 'status' => 'pending', 'priority' => 'low', 'created_at' => '2026-04-11 12:00:00']);
        $this->task($user, ['title' => 'Wrong status', 'status' => 'completed', 'priority' => 'high', 'created_at' => '2026-04-11 12:00:00']);
        $this->task($user, ['title' => 'Outside end', 'created_at' => '2026-04-13 00:00:00']);

        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/v1/tasks/reports?start_date=2026-04-10&end_date=2026-04-12&status=pending&priority=high')
            ->assertOk()->assertJsonPath('data.metrics.total', 2)
            ->assertJsonPath('data.metrics.pending', 2)
            ->assertJsonPath('data.metrics.completion_rate', 0)
            ->assertJsonPath('data.creation_trend.0.count', 1)
            ->assertJsonPath('data.creation_trend.2.count', 1);
        $this->getJson('/api/v1/tasks/reports?start_date=2026-04-10&end_date=2026-04-12&status=invalid')->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->getJson('/api/v1/tasks/reports?start_date=2026-04-10&end_date=2026-04-12&priority=invalid')->assertUnprocessable()->assertJsonValidationErrors(['priority']);
        $this->getJson('/api/v1/tasks/reports?start_date=2026-04-12&end_date=2026-04-10')->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
        $this->getJson('/api/v1/tasks/reports?start_date=2026-04-10')->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
    }

    public function test_report_limits_the_range_to_180_inclusive_dates_and_calculates_overdue_safely(): void
    {
        $user = $this->user();
        $today = now()->toDateString();
        $this->task($user, ['title' => 'Active overdue', 'due_date' => now()->subDay()->toDateString(), 'status' => 'in_progress', 'created_at' => now()->toDateTimeString()]);
        $this->task($user, ['title' => 'Completed overdue', 'due_date' => now()->subDay()->toDateString(), 'status' => 'completed', 'created_at' => now()->toDateTimeString()]);
        $this->task($user, ['title' => 'No due date', 'created_at' => now()->toDateTimeString()]);

        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/v1/tasks/reports?start_date=2026-01-01&end_date=2026-06-29')->assertOk();
        $this->getJson('/api/v1/tasks/reports?start_date=2026-01-01&end_date=2026-06-30')->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
        $this->getJson('/api/v1/tasks/reports?start_date='.$today.'&end_date='.$today)->assertOk()->assertJsonPath('data.metrics.overdue', 1);
    }
}
