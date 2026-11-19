<?php

namespace Tests\Feature\User\Tasks;

use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserTaskCalendarTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('tasks'), 'Run the task migration before the feature tests.');
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => 'user', 'status' => 'active']);
    }

    private function task(User $owner, array $attributes = []): Task
    {
        return Task::query()->create($attributes + [
            'title' => 'Calendar task',
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => '2026-06-15',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
        ]);
    }

    public function test_calendar_endpoint_requires_active_user_role_and_authentication(): void
    {
        $this->getJson('/api/v1/tasks/calendar?start_date=2026-06-01&end_date=2026-06-30')->assertUnauthorized();

        $inactive = $this->user(['status' => 'inactive']);
        $this->actingAs($inactive, 'sanctum')->getJson('/api/v1/tasks/calendar?start_date=2026-06-01&end_date=2026-06-30')->assertForbidden();

        $admin = $this->user(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/tasks/calendar?start_date=2026-06-01&end_date=2026-06-30')->assertForbidden();
    }

    public function test_calendar_returns_owned_and_assigned_tasks_but_not_private_tasks(): void
    {
        $user = $this->user();
        $other = $this->user();
        $owned = $this->task($user, ['title' => 'Owned task']);
        $assigned = $this->task($other, ['title' => 'Assigned task', 'assigned_user_id' => $user->id]);
        $this->task($other, ['title' => 'Private task']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks/calendar?start_date=2026-06-01&end_date=2026-06-30');
        $response->assertOk()->assertJsonStructure(['data' => [['id', 'title', 'status', 'priority', 'due_date', 'is_owned']]])
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $owned->id)
            ->assertJsonPath('data.0.is_owned', true)->assertJsonPath('data.1.id', $assigned->id)
            ->assertJsonPath('data.1.is_owned', false)->assertJsonMissing(['title' => 'Private task']);
    }

    public function test_calendar_validates_required_dates_inclusive_range_and_180_date_limit(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/tasks/calendar?end_date=2026-06-30')->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
        $this->getJson('/api/v1/tasks/calendar?start_date=2026-06-01')->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
        $this->getJson('/api/v1/tasks/calendar?start_date=06-01-2026&end_date=2026-06-30')->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
        $this->getJson('/api/v1/tasks/calendar?start_date=2026-07-01&end_date=2026-06-30')->assertUnprocessable()->assertJsonValidationErrors(['end_date']);

        $start = CarbonImmutable::parse('2026-01-01');
        $maximumEnd = $start->addDays(179)->toDateString();
        $tooWideEnd = $start->addDays(180)->toDateString();
        $this->getJson('/api/v1/tasks/calendar?start_date=2026-01-01&end_date='.$maximumEnd)->assertOk();
        $this->getJson('/api/v1/tasks/calendar?start_date=2026-01-01&end_date='.$tooWideEnd)->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
    }

    public function test_calendar_filters_status_priority_and_their_combination(): void
    {
        $user = $this->user();
        $this->task($user, ['title' => 'Pending urgent', 'priority' => 'urgent']);
        $this->task($user, ['title' => 'Pending low', 'priority' => 'low']);
        $this->task($user, ['title' => 'Completed urgent', 'status' => 'completed', 'priority' => 'urgent']);
        $this->task($user, ['title' => 'No due date', 'due_date' => null]);
        $this->actingAs($user, 'sanctum');
        $range = '?start_date=2026-06-01&end_date=2026-06-30';

        $this->getJson('/api/v1/tasks/calendar'.$range)->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/tasks/calendar'.$range.'&status=pending')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/tasks/calendar'.$range.'&priority=urgent')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/tasks/calendar'.$range.'&status=pending&priority=urgent')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Pending urgent');
        $this->getJson('/api/v1/tasks/calendar'.$range.'&status=invalid')->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->getJson('/api/v1/tasks/calendar'.$range.'&priority=invalid')->assertUnprocessable()->assertJsonValidationErrors(['priority']);
    }

    public function test_calendar_includes_both_range_boundaries_serializes_date_only_and_excludes_soft_deleted_tasks(): void
    {
        $user = $this->user();
        $this->task($user, ['title' => 'Start boundary', 'due_date' => '2026-06-01']);
        $this->task($user, ['title' => 'End boundary', 'due_date' => '2026-06-30', 'status' => 'completed']);
        $deleted = $this->task($user, ['title' => 'Deleted task', 'due_date' => '2026-06-15']);
        $deleted->delete();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks/calendar?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.due_date', '2026-06-01')
            ->assertJsonPath('data.1.due_date', '2026-06-30')->assertJsonPath('data.1.status', 'completed');
    }

    public function test_calendar_returns_all_matching_tasks_without_a_100_item_page_limit(): void
    {
        $user = $this->user();
        for ($index = 0; $index < 105; $index++) {
            $this->task($user, ['title' => 'Calendar task '.$index]);
        }

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks/calendar?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()->assertJsonCount(105, 'data');
    }
}
