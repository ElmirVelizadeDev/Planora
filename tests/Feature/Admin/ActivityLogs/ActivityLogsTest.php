<?php

namespace Tests\Feature\Admin\ActivityLogs;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\Api\V1\Admin\CategoryManagementService;
use App\Services\Api\V1\Admin\UserManagementService;
use App\Services\Api\V1\Projects\ProjectService;
use App\Services\Api\V1\Tasks\TaskService;
use App\Services\Api\V1\Admin\UserActivityLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ActivityLogsTest extends TestCase
{
    use DatabaseTransactions;

    private function account(string $role = 'user', array $permissions = []): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'moderator_permissions' => $role === 'moderator' ? $permissions : null,
        ]);
    }

    private function log(array $attributes = []): UserActivityLog
    {
        return UserActivityLog::query()->create(array_merge([
            'event_id' => (string) Str::uuid(),
            'user_id' => null,
            'actor_id' => null,
            'event' => 'project.created',
            'description' => 'Created project Alpha',
            'module' => 'Projects',
            'subject_type' => 'Project',
            'subject_id' => 10,
            'subject_label' => 'Alpha',
            'context' => null,
            'old_values' => null,
            'new_values' => ['name' => 'Alpha'],
            'actor_name' => null,
            'actor_email' => null,
            'actor_role' => null,
            'ip_address' => '192.0.2.1',
            'user_agent' => 'Planora test browser',
            'request_method' => 'POST',
            'request_path' => '/api/v1/admin/projects',
            'request_id' => 'test-request-1',
            'status' => 'success',
            'created_at' => now(),
        ], $attributes));
    }

    public function test_activity_logs_require_authorized_admin_or_moderator_permission(): void
    {
        $this->getJson('/api/v1/admin/activity-logs')->assertUnauthorized();
        $this->getJson('/api/v1/admin/activity-logs/filters')->assertUnauthorized();
        $this->getJson('/api/v1/admin/activity-logs/statistics')->assertUnauthorized();
        $this->getJson('/api/v1/admin/activity-logs/export')->assertUnauthorized();

        $this->actingAs($this->account('user'), 'sanctum')
            ->getJson('/api/v1/admin/activity-logs')->assertForbidden();

        $moderator = $this->account('moderator', ['users.view']);
        $this->actingAs($moderator, 'sanctum')->getJson('/api/v1/admin/activity-logs')->assertForbidden();

        $this->actingAs($this->account('admin'), 'sanctum')
            ->getJson('/api/v1/admin/activity-logs')->assertOk();
    }

    public function test_activity_logs_spa_fallback_allows_admin_and_permitted_moderator_only(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($admin)->get('/admin/activity-logs')->assertOk()->assertSee('<div id="app"></div>', false);
        $this->get('/admin/activity-logs/123')->assertOk()->assertSee('<div id="app"></div>', false);

        $permittedModerator = $this->account('moderator', ['activity.view']);
        $this->actingAs($permittedModerator)->get('/admin/activity-logs')->assertOk();

        $deniedModerator = $this->account('moderator', ['users.view']);
        $this->actingAs($deniedModerator)->get('/admin/activity-logs')->assertForbidden();

        $regularUser = $this->account('user');
        $this->actingAs($regularUser)->get('/admin/activity-logs')->assertRedirect('/dashboard');

        auth()->logout();
        $this->get('/admin/activity-logs')->assertRedirect('/login');
    }

    public function test_moderator_with_activity_permission_only_sees_user_and_authentication_records_for_non_admin_accounts(): void
    {
        $regular = $this->account();
        $admin = $this->account('admin');
        $this->log(['user_id' => $regular->id, 'subject_type' => 'User', 'subject_id' => $regular->id, 'subject_label' => $regular->name, 'event' => 'user.profile_updated', 'module' => 'Users']);
        $this->log(['user_id' => $admin->id, 'subject_type' => 'User', 'subject_id' => $admin->id, 'subject_label' => $admin->name, 'event' => 'user.profile_updated', 'module' => 'Users']);
        $this->log(['event' => 'project.created', 'module' => 'Projects']);
        $moderator = $this->account('moderator', ['activity.view']);

        $response = $this->actingAs($moderator, 'sanctum')->getJson('/api/v1/admin/activity-logs')->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('user.profile_updated', $response->json('data.0.action'));
        $this->assertSame($regular->id, $response->json('data.0.target.id'));
    }

    public function test_admin_can_filter_sort_paginate_and_get_matching_statistics(): void
    {
        $actor = $this->account();
        foreach (range(1, 12) as $index) {
            $this->log([
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_email' => $actor->email,
                'event' => $index % 2 ? 'task.created' : 'task.completed',
                'module' => 'Tasks',
                'subject_type' => 'Task',
                'description' => 'Regression search '.$index,
                'status' => $index === 1 ? 'failure' : 'success',
                'created_at' => now()->subDays($index % 3),
            ]);
        }

        $response = $this->actingAs($this->account('admin'), 'sanctum')
            ->getJson('/api/v1/admin/activity-logs?search=Regression&module=Tasks&subject_type=Task&actor_id='.$actor->id.'&per_page=10&sort=event&direction=asc')
            ->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.statistics.total_activities', 12)
            ->assertJsonPath('meta.statistics.unique_actors', 1);

        $this->assertCount(10, $response->json('data'));
        $this->assertSame('task.completed', $response->json('data.0.action'));
        $this->assertContains('Task', $this->getJson('/api/v1/admin/activity-logs/filters')->json('data.subject_types'));
        $this->getJson('/api/v1/admin/activity-logs?subject_type=Invalid')->assertUnprocessable()->assertJsonValidationErrors('subject_type');
        $this->getJson('/api/v1/admin/activity-logs?from=2026-11-10&to=2026-11-01')->assertUnprocessable();
        $this->getJson('/api/v1/admin/activity-logs?from=2025-10-01&to=2026-11-13')->assertUnprocessable();
    }

    public function test_legacy_module_values_sort_by_their_resolved_module(): void
    {
        $this->log(['event' => 'user.profile_updated', 'module' => null]);
        $this->log(['event' => 'auth.login', 'module' => null]);
        $this->log(['event' => 'settings.updated', 'module' => null]);

        $response = $this->actingAs($this->account('admin'), 'sanctum')
            ->getJson('/api/v1/admin/activity-logs?sort=module&direction=asc')
            ->assertOk();

        $this->assertSame('auth.login', $response->json('data.0.action'));
        $this->assertSame('Authentication', $response->json('data.0.module'));
        $this->assertSame('settings.updated', $response->json('data.1.action'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/activity-logs?module=Settings')->json('meta.total'));
    }

    public function test_admin_can_view_actual_event_details_and_export_filtered_utf8_csv_safely(): void
    {
        $actor = $this->account('admin');
        $log = $this->log([
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'description' => '=HYPERLINK("https://example.invalid") – layihə',
            'context' => ['session_id' => 'legacy-session-marker'],
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'completed'],
        ]);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/admin/activity-logs/'.$log->id)
            ->assertOk()
            ->assertJsonPath('data.old_values.status', 'pending')
            ->assertJsonPath('data.new_values.status', 'completed')
            ->assertJsonPath('data.properties', [])
            ->assertJsonPath('data.request.request_id', 'test-request-1')
            ->assertJsonPath('data.actor.name', $actor->name);

        $csv = $this->get('/api/v1/admin/activity-logs/export?module=Projects&subject_type=Project');
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->streamedContent());
        $this->assertStringContainsString("'=HYPERLINK", $csv->streamedContent());
        $this->assertStringContainsString('layihə', $csv->streamedContent());
    }

    public function test_logger_redacts_sensitive_metadata_and_skips_unchanged_values(): void
    {
        $actor = $this->account('admin');
        $logger = app(UserActivityLogger::class);
        $record = $logger->record('user.profile_updated', 'Profile updated.', $actor, $actor, [
            'email' => 'safe@example.test', 'password' => 'NeverPersistThis',
            'nested' => ['otp_code' => '123456', 'session_id' => 'session-secret-marker'],
        ], oldValues: ['status' => 'inactive', 'password' => 'OldSecret'], newValues: ['status' => 'active', 'access_token' => 'NewSecret']);

        $this->assertSame(['email' => 'safe@example.test', 'nested' => []], $record->context);
        $this->assertSame(['status' => 'inactive'], $record->old_values);
        $this->assertSame(['status' => 'active'], $record->new_values);
        $this->assertSame(0, UserActivityLog::query()->where('description', 'No change')->count());
        $this->assertNull($logger->recordChange('user.profile_updated', 'No change', $actor, $actor, ['name' => 'Same'], ['name' => 'Same']));
        $this->assertDatabaseMissing('user_activity_logs', ['description' => 'No change']);
    }

    public function test_domain_mutations_and_report_exports_are_audited(): void
    {
        $admin = $this->account('admin');
        $owner = $this->account();

        $users = app(UserManagementService::class);
        $managedUser = $users->create($admin, [
            'name' => 'Managed Audit User',
            'email' => 'managed-audit-user@example.test',
            'password' => 'StrongPassword123!',
            'role' => 'user',
            'status' => 'active',
        ]);
        $users->changeStatus($admin, $managedUser, 'suspended', 'Audit test');
        $statusEvent = UserActivityLog::query()
            ->where('event', 'account.suspended')
            ->where('user_id', $managedUser->id)
            ->firstOrFail();
        $this->assertStringNotContainsString('Audit test', json_encode($statusEvent->getAttributes(), JSON_THROW_ON_ERROR));

        $categories = app(CategoryManagementService::class);
        $category = $categories->create(['name' => 'Audit category', 'color' => '#123ABC'], $admin);
        $categories->update($category, ['name' => 'Updated audit category'], $admin);

        $project = app(ProjectService::class)->create([
            'name' => 'Audit project', 'status' => 'planned', 'owner_id' => $owner->id,
        ], $admin);
        app(ProjectService::class)->update($project, ['status' => 'active'], $admin);

        $task = app(TaskService::class)->create([
            'title' => 'Audit task', 'status' => 'pending', 'priority' => 'medium',
            'owner_id' => $owner->id, 'created_by' => $admin->id,
        ], $admin);
        app(TaskService::class)->update($task, ['status' => 'completed'], $admin);

        foreach ([
            'user.created', 'account.suspended', 'category.created', 'category.updated',
            'project.created', 'project.status_changed', 'task.created', 'task.completed',
        ] as $event) {
            $this->assertDatabaseHas('user_activity_logs', ['event' => $event, 'actor_id' => $admin->id]);
        }

        $this->actingAs($admin, 'sanctum')->get('/api/v1/admin/reports/export')->assertOk()->streamedContent();
        $this->assertDatabaseHas('user_activity_logs', [
            'event' => 'report.exported',
            'actor_id' => $admin->id,
            'module' => 'Reports',
        ]);
    }

    public function test_audit_failure_rolls_back_the_business_mutation(): void
    {
        $this->mock(UserActivityLogger::class, function ($mock): void {
            $mock->shouldReceive('recordChange')->once()->andThrow(new RuntimeException('Audit write failed.'));
        });

        try {
            app(CategoryManagementService::class)->create(
                ['name' => 'Must roll back', 'color' => '#123ABC'],
                $this->account('admin'),
            );
            $this->fail('The audit failure should be surfaced to the caller.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit write failed.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('categories', ['name' => 'Must roll back']);
    }

    public function test_failed_login_is_audited_without_persisting_submitted_credentials(): void
    {
        $secretEmail = 'private-login@example.test';
        $this->withHeader('X-Request-ID', 'access-token-secret-marker')
            ->postJson('/api/v1/auth/login', ['email' => $secretEmail, 'password' => 'NotARealPassword_123!'])
            ->assertUnprocessable();

        $event = UserActivityLog::query()->where('event', 'auth.login_failed')->latest('id')->firstOrFail();
        $this->assertSame('failure', $event->status);
        $this->assertNull($event->actor_id);
        $this->assertNull($event->user_id);
        $this->assertNull($event->request_id);
        $this->assertStringNotContainsString($secretEmail, json_encode($event->getAttributes(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('NotARealPassword', json_encode($event->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_successful_login_is_audited_with_actor_and_subject_snapshots(): void
    {
        $user = $this->account();

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();

        $event = UserActivityLog::query()->where('event', 'auth.login')->latest('id')->firstOrFail();
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame($user->email, $event->actor_email);
        $this->assertSame('POST', $event->request_method);
    }
}
