<?php

namespace Tests\Feature\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Api\V1\Notifications\NotificationService;
use App\Services\Api\V1\Tasks\TaskService;
use App\Services\Api\V1\Tasks\UserTaskService;
use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(MaintenanceScopeStore::class)->reset();
    }

    public function test_notification_api_requires_an_active_user_or_admin_and_scopes_records_to_the_recipient(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $otherUser = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $own = $this->notification($user);
        $other = $this->notification($otherUser);

        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications?filter=unread')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->putJson("/api/v1/notifications/{$other->id}/read")->assertNotFound();
        $this->putJson("/api/v1/notifications/{$own->id}/read")
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 0);

        $inactive = User::factory()->create(['role' => 'user', 'status' => 'inactive']);
        $this->actingAs($inactive, 'sanctum')->getJson('/api/v1/notifications')->assertForbidden();
    }

    public function test_admins_can_only_access_notifications_addressed_to_them_and_moderators_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $other = $this->notification($otherAdmin);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/notifications')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/notifications/{$other->id}/read")->assertNotFound();

        $moderator = User::factory()->create(['role' => 'moderator', 'status' => 'active']);
        $this->actingAs($moderator, 'sanctum')->getJson('/api/v1/notifications')->assertForbidden();
    }

    public function test_mark_all_as_read_updates_only_the_authenticated_recipients_notifications(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->notification($user);
        $this->notification($user);
        $otherNotification = $this->notification($other);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/notifications/read-all')
            ->assertOk()->assertJsonPath('data.unread_count', 0)->assertJsonPath('data.updated', 2);

        $this->assertSame(0, UserNotification::query()->where('recipient_id', $user->id)->whereNull('read_at')->count());
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_task_assignment_status_and_due_notifications_are_idempotent_and_do_not_notify_the_actor(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $assignee = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $tasks = app(TaskService::class);
        $task = $tasks->create([
            'title' => 'Prepare launch checklist', 'status' => 'pending', 'priority' => 'medium',
            'due_date' => now()->addDay()->toDateString(), 'owner_id' => $owner->id,
            'assigned_user_id' => null,
        ], $admin);
        $task = $tasks->update($task, ['assigned_user_id' => $assignee->id], $admin);
        $service = app(NotificationService::class);

        $service->taskAssigned($task, $assignee, $admin);
        $service->taskAssigned($task, $assignee, $admin);
        $service->taskAssigned($task, $admin, $admin);
        app(UserTaskService::class)->updateStatus($task, 'completed', $assignee);
        $service->taskStatusChanged($task, $assignee, 'completed');
        $service->taskStatusChanged($task, $owner, 'completed');
        $dueTask = Task::query()->create([
            'title' => 'Review deployment plan', 'status' => 'pending', 'priority' => 'medium',
            'due_date' => now()->addDay()->toDateString(), 'owner_id' => $owner->id,
            'assigned_user_id' => $assignee->id,
        ]);
        $service->taskDueSoon($dueTask->id);
        $service->taskDueSoon($dueTask->id);

        $this->assertSame(2, UserNotification::query()->where('recipient_id', $assignee->id)->count());
        $this->assertSame(1, UserNotification::query()->where('recipient_id', $owner->id)->where('type', 'task.completed')->count());
        $this->assertSame(0, UserNotification::query()->where('recipient_id', $admin->id)->count());

        $this->actingAs($assignee, 'sanctum')->getJson('/api/v1/notifications')
            ->assertOk()->assertJsonPath('data.0.destination.name', 'tasks.show');
    }

    public function test_registration_and_maintenance_events_only_notify_other_active_admins_without_sensitive_details(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $inactiveAdmin = User::factory()->create(['role' => 'admin', 'status' => 'inactive']);
        $newUser = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $service = app(NotificationService::class);

        $service->userRegistered($newUser);
        $service->userRegistered($newUser);
        $secret = 'never-store-secret-entry-value';
        $service->maintenanceChanged($actor, ['message' => 'old'], ['message' => $secret]);

        $this->assertSame(2, UserNotification::query()->where('recipient_id', $otherAdmin->id)->count());
        $this->assertSame(0, UserNotification::query()->where('recipient_id', $actor->id)->count());
        $this->assertSame(0, UserNotification::query()->where('recipient_id', $inactiveAdmin->id)->count());
        $this->assertDatabaseMissing('user_notifications', ['description' => $secret]);
    }

    public function test_deleted_or_inaccessible_task_is_retained_as_readable_notification_without_destination(): void
    {
        $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $assignee = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $task = Task::query()->create([
            'title' => 'Retained task title', 'status' => 'pending', 'priority' => 'medium',
            'owner_id' => $owner->id, 'assigned_user_id' => $assignee->id,
        ]);
        app(NotificationService::class)->taskAssigned($task, $assignee, $owner);
        $task->delete();

        $this->actingAs($assignee, 'sanctum')->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Task assigned to you')
            ->assertJsonPath('data.0.destination', null);
    }

    private function notification(User $recipient): UserNotification
    {
        return UserNotification::query()->create([
            'recipient_id' => $recipient->id,
            'type' => 'task.assigned',
            'title' => 'Task assigned to you',
            'description' => 'A task was assigned to you.',
            'deduplication_key' => hash('sha256', (string) Str::uuid()),
        ]);
    }
}
