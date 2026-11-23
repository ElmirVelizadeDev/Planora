<?php

namespace Tests\Feature\Notifications;

use App\Models\EmailVerificationOtp;
use App\Models\PendingRegistration;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(MaintenanceScopeStore::class)->reset();
    }

    public function test_registration_notifications_are_created_only_after_successful_otp_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $email = 'verified-'.fake()->unique()->uuid().'@example.test';
        $this->withHeaders(['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/']);
        $this->seedPendingRegistration($email, '246810', now()->addMinutes(5));

        $this->postJson('/api/v1/auth/register/verify', ['email' => $email, 'code' => '111111'])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertSame(0, UserNotification::query()->where('recipient_id', $admin->id)->count());

        $this->postJson('/api/v1/auth/register/verify', ['email' => $email, 'code' => '246810'])
            ->assertOk();
        $this->assertDatabaseHas('users', ['email' => $email, 'status' => 'active']);
        $this->assertSame(1, UserNotification::query()->where('recipient_id', $admin->id)->where('type', 'admin.user_registered')->count());

        $this->postJson('/api/v1/auth/register/verify', ['email' => $email, 'code' => '246810'])
            ->assertUnprocessable();
        $this->assertSame(1, UserNotification::query()->where('recipient_id', $admin->id)->where('type', 'admin.user_registered')->count());
    }

    public function test_expired_registration_otp_does_not_create_an_account_or_admin_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $email = 'expired-'.fake()->unique()->uuid().'@example.test';
        $this->seedPendingRegistration($email, '135790', now()->subMinute());

        $this->postJson('/api/v1/auth/register/verify', ['email' => $email, 'code' => '135790'])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertSame(0, UserNotification::query()->where('recipient_id', $admin->id)->count());
    }

    public function test_due_reminder_command_checks_current_task_eligibility_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 07:59:00', 'UTC'));

        try {
            $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
            $oldAssignee = User::factory()->create(['role' => 'user', 'status' => 'active']);
            $currentAssignee = User::factory()->create(['role' => 'user', 'status' => 'active']);
            $eligible = $this->task($owner, $oldAssignee, 'Eligible after reassignment', '2026-11-16');
            $eligible->update(['assigned_user_id' => $currentAssignee->id]);
            $completed = $this->task($owner, $currentAssignee, 'Already completed', '2026-11-16', 'completed');
            $deleted = $this->task($owner, $currentAssignee, 'Soft deleted', '2026-11-16');
            $deleted->delete();
            $changedDate = $this->task($owner, $currentAssignee, 'Date changed', '2026-11-16');
            $changedDate->update(['due_date' => '2026-11-17']);

            $this->artisan('planora:notify-tasks-due-soon')->assertExitCode(0);
            $this->artisan('planora:notify-tasks-due-soon')->assertExitCode(0);

            $this->assertSame(1, UserNotification::query()->where('type', 'task.due_soon')->where('related_id', $eligible->id)->count());
            $this->assertSame(0, UserNotification::query()->where('type', 'task.due_soon')->where('related_id', $completed->id)->count());
            $this->assertSame(0, UserNotification::query()->where('type', 'task.due_soon')->where('related_id', $deleted->id)->count());
            $this->assertSame(0, UserNotification::query()->where('type', 'task.due_soon')->where('related_id', $changedDate->id)->count());
            $this->assertSame(0, UserNotification::query()->where('recipient_id', $oldAssignee->id)->where('type', 'task.due_soon')->count());
            $this->assertSame(1, UserNotification::query()->where('recipient_id', $currentAssignee->id)->where('type', 'task.due_soon')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_maintenance_endpoint_notifies_other_active_admins_only_after_authorized_change(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $inactiveAdmin = User::factory()->create(['role' => 'admin', 'status' => 'inactive']);
        $regularUser = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $secret = '/never-store-this-admin-entry-secret';
        $payload = ['enabled' => true, 'message' => 'Maintenance notice '.$secret];
        $existingNotificationIds = UserNotification::query()->pluck('id')->all();

        $this->patchJson('/api/v1/admin/system-maintenance/scopes/public-website', $payload)->assertUnauthorized();
        $this->actingAs($regularUser, 'sanctum')
            ->patchJson('/api/v1/admin/system-maintenance/scopes/public-website', $payload)
            ->assertForbidden();
        $this->assertSame(count($existingNotificationIds), UserNotification::query()->count());

        $this->actingAs($actor, 'sanctum');
        $this->grantAdminStepUp('system.maintenance', $actor);
        $this->actingAs($actor, 'sanctum')
            ->patchJson('/api/v1/admin/system-maintenance/scopes/public-website', $payload)
            ->assertOk();

        $newNotifications = UserNotification::query()->whereNotIn('id', $existingNotificationIds)->get();
        $this->assertSame(0, $newNotifications->where('recipient_id', $actor->id)->count());
        $this->assertSame(1, $newNotifications->where('recipient_id', $otherAdmin->id)->where('type', 'admin.maintenance_changed')->count());
        $this->assertSame(0, $newNotifications->where('recipient_id', $inactiveAdmin->id)->count());
        $this->assertFalse($newNotifications->contains('description', 'Maintenance notice '.$secret));
        $this->assertStringNotContainsString($secret, $newNotifications->pluck('description')->implode(' '));
    }

    private function seedPendingRegistration(string $email, string $code, Carbon $expiresAt): void
    {
        PendingRegistration::query()->create([
            'name' => 'OTP Test User',
            'email' => $email,
            'password_hash' => Hash::make('Valid-password-928!'),
            'expires_at' => $expiresAt,
        ]);
        EmailVerificationOtp::query()->create([
            'email' => $email,
            'otp_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
            'attempts' => 0,
        ]);
    }

    private function task(User $owner, User $assignee, string $title, string $dueDate, string $status = 'pending'): Task
    {
        return Task::query()->create([
            'title' => $title,
            'status' => $status,
            'priority' => 'medium',
            'due_date' => $dueDate,
            'owner_id' => $owner->id,
            'assigned_user_id' => $assignee->id,
        ]);
    }
}
