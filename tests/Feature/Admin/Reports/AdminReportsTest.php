<?php

namespace Tests\Feature\Admin\Reports;

use App\Models\Category;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_reports_endpoints_are_admin_only(): void
    {
        $this->getJson('/api/v1/admin/reports')->assertUnauthorized();

        foreach (['user', 'moderator'] as $role) {
            $account = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($account, 'sanctum')->getJson('/api/v1/admin/reports')->assertForbidden();
            $this->getJson('/api/v1/admin/reports/filters?type=users')->assertForbidden();
            $this->getJson('/api/v1/admin/reports/tables/tasks')->assertForbidden();
            $this->getJson('/api/v1/admin/reports/export')->assertForbidden();
        }

        $admin = $this->admin();
        $visibleUser = User::factory()->create(['name' => 'Visible Filter User']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/reports')->assertOk();
        $options = $this->getJson('/api/v1/admin/reports/filters?type=users&search=Visible')
            ->assertOk()->assertJsonPath('data.0.name', 'Visible Filter User');
        $this->assertArrayNotHasKey('email', $options->json('data.0'));
        $today = now()->toDateString();
        $table = $this->getJson('/api/v1/admin/reports/tables/registrations?from='.$today.'&to='.$today.'&user_id='.$visibleUser->id)
            ->assertOk()->assertJsonPath('data.data.0.name', 'Visible Filter User');
        $this->assertArrayNotHasKey('email', $table->json('data.data.0'));
    }

    public function test_overview_uses_real_records_and_scopes_filters_and_periods(): void
    {
        $owner = User::factory()->create(['created_at' => '2026-05-02 10:00:00']);
        $other = User::factory()->create(['created_at' => '2026-04-20 10:00:00']);
        $category = Category::factory()->create(['name' => 'Delivery']);
        $archived = Category::factory()->create(['name' => 'Archived delivery']);
        $archived->delete();

        $project = Project::query()->forceCreate([
            'name' => 'Plan delivery', 'description' => null, 'status' => 'active',
            'owner_id' => $owner->id, 'category_id' => $category->id,
            'created_at' => '2026-05-03 12:00:00', 'updated_at' => '2026-05-03 12:00:00',
        ]);
        Task::query()->forceCreate([
            'title' => 'Open task', 'status' => 'pending', 'priority' => 'high',
            'due_date' => now()->subDay()->toDateString(), 'owner_id' => $owner->id, 'assigned_user_id' => $other->id,
            'created_by' => $owner->id, 'project_id' => $project->id, 'category_id' => $category->id,
            'created_at' => '2026-05-03 13:00:00', 'updated_at' => '2026-05-03 13:00:00',
        ]);
        Task::query()->forceCreate([
            'title' => 'Completed task', 'status' => 'completed', 'priority' => 'low',
            'due_date' => '2026-05-01', 'owner_id' => $owner->id, 'assigned_user_id' => $owner->id,
            'created_by' => $owner->id, 'project_id' => $project->id, 'category_id' => $archived->id,
            'created_at' => '2026-05-03 14:00:00', 'updated_at' => '2026-05-03 14:00:00',
        ]);
        Task::query()->forceCreate([
            'title' => 'Unrelated task', 'status' => 'pending', 'priority' => 'urgent',
            'due_date' => '2026-05-04', 'owner_id' => $other->id, 'assigned_user_id' => $other->id,
            'created_by' => $other->id, 'project_id' => $project->id, 'category_id' => $category->id,
            'created_at' => '2026-05-03 15:00:00', 'updated_at' => '2026-05-03 15:00:00',
        ]);

        $this->actingAs($this->admin(), 'sanctum');
        $response = $this->getJson('/api/v1/admin/reports?from=2026-05-01&to=2026-05-07&user_id='.$owner->id.'&project_id='.$project->id.'&category_id='.$category->id)
            ->assertOk()
            ->assertJsonPath('data.period.days', 7)
            ->assertJsonPath('data.overview.users.total', 1)
            ->assertJsonPath('data.overview.users.active', 1)
            ->assertJsonPath('data.overview.users.new_in_period', 1)
            ->assertJsonPath('data.overview.users.growth_percent', null)
            ->assertJsonPath('data.user_analytics.registration_trend.1.value', 1)
            ->assertJsonPath('data.overview.projects.total', 1)
            ->assertJsonPath('data.overview.projects.active', 1)
            ->assertJsonPath('data.overview.projects.completion_rate', 0)
            ->assertJsonPath('data.project_analytics.creation_trend.2.value', 1)
            ->assertJsonPath('data.overview.tasks.total', 1)
            ->assertJsonPath('data.overview.tasks.pending', 1)
            ->assertJsonPath('data.overview.tasks.completion_rate', 0)
            ->assertJsonPath('data.task_analytics.creation_trend.2.value', 1)
            ->assertJsonPath('data.overview.tasks.overdue', 1)
            ->assertJsonPath('data.category_analytics.categories.data.0.projects', 1)
            ->assertJsonPath('data.category_analytics.categories.data.0.tasks', 1)
            ->assertJsonPath('data.task_analytics.recent_tasks.data.0.title', 'Open task')
            ->assertJsonPath('data.task_analytics.completion_history_available', false);
        $this->assertGreaterThanOrEqual(1, $response->json('data.overview.categories.active'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.overview.categories.archived'));

        $this->getJson('/api/v1/admin/reports?from=2026-05-01&to=2026-05-07&user_id='.$owner->id.'&project_id='.$project->id.'&category_id='.$archived->id)
            ->assertOk()->assertJsonPath('data.category_analytics.categories.data.0.status', 'archived')
            ->assertJsonPath('data.category_analytics.categories.data.0.tasks', 1);

        $this->getJson('/api/v1/admin/reports?from=2026-05-01&to=2026-05-07&user_id='.$owner->id.'&project_id='.$project->id.'&category_id='.$category->id.'&task_status=completed')
            ->assertOk()->assertJsonPath('data.overview.tasks.total', 0)
            ->assertJsonPath('data.task_analytics.status_distribution.2.value', 0)
            ->assertJsonPath('data.category_analytics.categories.data.0.tasks', 0);

        $this->getJson('/api/v1/admin/reports?from=2026-05-01&to=2026-05-07&user_id='.$owner->id.'&project_id='.$project->id)
            ->assertOk()->assertJsonPath('data.overview.tasks.total', 2)
            ->assertJsonPath('data.overview.tasks.overdue', 1);
    }

    public function test_overview_handles_empty_metrics_and_rejects_invalid_date_ranges(): void
    {
        $this->actingAs($this->admin(), 'sanctum');
        $this->getJson('/api/v1/admin/reports?from=2026-05-01&to=2026-05-07&user_id=999999')
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->getJson('/api/v1/admin/reports?from=2026-05-08&to=2026-05-01')
            ->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/admin/reports?from=2020-01-01')
            ->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/admin/reports?to='.now()->toDateString())->assertOk();

        $isolatedOwner = User::factory()->create();
        $empty = app(\App\Services\Api\V1\Admin\AdminReportService::class)->build([
            'from' => '2000-01-01', 'to' => '2000-01-07', 'user_id' => $isolatedOwner->id,
        ]);
        $this->assertNull($empty['overview']['projects']['completion_rate']);
        $this->assertNull($empty['overview']['tasks']['completion_rate']);
        $this->assertNull($empty['overview']['users']['growth_percent']);
        $this->assertSame(7, $empty['period']['days']);
    }

    public function test_previous_period_comparison_uses_an_equal_inclusive_range(): void
    {
        $owner = User::factory()->create();
        $periodProject = Project::query()->forceCreate([
            'name' => 'Current period project', 'status' => 'active', 'owner_id' => $owner->id,
            'created_at' => '2026-05-01 00:00:00', 'updated_at' => '2026-05-01 00:00:00',
        ]);
        Project::query()->forceCreate([
            'name' => 'Previous period project', 'status' => 'planned', 'owner_id' => $owner->id,
            'created_at' => '2026-04-24 12:00:00', 'updated_at' => '2026-04-24 12:00:00',
        ]);
        Project::query()->forceCreate([
            'name' => 'Current period end project', 'status' => 'active', 'owner_id' => $owner->id,
            'created_at' => '2026-05-07 23:59:59', 'updated_at' => '2026-05-07 23:59:59',
        ]);

        foreach ([
            ['Current range start', '2026-05-01 00:00:00'],
            ['Current range end', '2026-05-07 23:59:59'],
            ['Previous range start', '2026-04-24 00:00:00'],
        ] as [$title, $createdAt]) {
            Task::query()->forceCreate([
                'title' => $title, 'status' => 'pending', 'priority' => 'medium',
                'owner_id' => $owner->id, 'created_by' => $owner->id,
                'project_id' => $periodProject->id, 'created_at' => $createdAt, 'updated_at' => $createdAt,
            ]);
        }

        $report = app(\App\Services\Api\V1\Admin\AdminReportService::class)->build([
            'from' => '2026-05-01', 'to' => '2026-05-07', 'user_id' => $owner->id,
        ]);
        $this->assertSame(7, $report['period']['days']);
        $this->assertSame('2026-04-24', $report['period']['previous_from']);
        $this->assertSame('2026-04-30', $report['period']['previous_to']);
        $this->assertSame(2, $report['overview']['projects']['new_in_period']);
        $this->assertSame(1, $report['overview']['projects']['previous_period']);
        $this->assertSame(100.0, $report['overview']['projects']['growth_percent']);
        $this->assertSame(2, $report['overview']['tasks']['created_in_period']);
        $this->assertSame(1, $report['overview']['tasks']['previous_period']);
        $this->assertSame(100.0, $report['overview']['tasks']['growth_percent']);
        $this->assertSame(1, $report['project_analytics']['creation_trend'][0]['value']);
        $this->assertSame(1, $report['project_analytics']['creation_trend'][6]['value']);
        $this->assertSame(1, $report['task_analytics']['creation_trend'][0]['value']);
        $this->assertSame(1, $report['task_analytics']['creation_trend'][6]['value']);
    }

    public function test_null_deadlines_and_uncategorized_records_are_counted_without_becoming_due_items(): void
    {
        $owner = User::factory()->create();
        $project = Project::query()->forceCreate([
            'name' => 'Uncategorized project', 'status' => 'planned', 'owner_id' => $owner->id,
            'category_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Task::query()->forceCreate([
            'title' => 'No deadline', 'status' => 'pending', 'priority' => 'low', 'due_date' => null,
            'owner_id' => $owner->id, 'assigned_user_id' => null, 'created_by' => $owner->id,
            'project_id' => $project->id, 'category_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $report = app(\App\Services\Api\V1\Admin\AdminReportService::class)->build([
            'from' => now()->toDateString(), 'to' => now()->toDateString(), 'user_id' => $owner->id,
        ]);

        $this->assertSame(1, $report['overview']['projects']['total']);
        $this->assertSame(1, $report['overview']['tasks']['total']);
        $this->assertSame(0, $report['overview']['tasks']['overdue']);
        $this->assertSame([], $report['task_analytics']['upcoming_deadlines']['data']);
        $this->assertSame(0, array_sum(array_column($report['project_analytics']['category_usage'], 'value')));
        $this->assertSame(0, array_sum(array_column($report['task_analytics']['category_usage'], 'value')));
    }

    public function test_csv_export_uses_filtered_report_data_and_neutralizes_formulas(): void
    {
        $formula = "=HYPERLINK(\"https://example.invalid\",\"Demo,\nTeam\")";
        $category = Category::factory()->create(['name' => $formula]);
        $this->actingAs($this->admin(), 'sanctum');

        $response = $this->get('/api/v1/admin/reports/export?from=2026-05-01&to=2026-05-07&category_id='.$category->id)
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $body = $response->streamedContent();

        $this->assertTrue(str_starts_with($body, "\xEF\xBB\xBF"));
        $this->assertStringContainsString('2026-05-01 to 2026-05-07', $body);
        $this->assertStringContainsString("'=HYPERLINK", $body);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, substr($body, 3));
        rewind($stream);
        $this->assertSame(['Section', 'Record ID', 'Name', 'Role', 'Status', 'Priority', 'Category', 'Project', 'Due date', 'Created at', 'Metric', 'Value', 'Period'], fgetcsv($stream, escape: '\\'));
        $categoryRow = null;
        while (($row = fgetcsv($stream, escape: '\\')) !== false) {
            if (($row[0] ?? null) === 'Category usage' && (int) ($row[1] ?? 0) === $category->id) {
                $categoryRow = $row;
                break;
            }
        }
        fclose($stream);
        $this->assertNotNull($categoryRow);
        $this->assertSame("'".$formula, $categoryRow[2]);
    }

    public function test_report_detail_tables_search_sort_and_paginate_on_the_server(): void
    {
        $owner = User::factory()->create();
        for ($index = 1; $index <= 12; $index++) {
            Task::query()->forceCreate([
                'title' => sprintf('Report Task %02d', $index), 'status' => 'pending', 'priority' => 'medium',
                'owner_id' => $owner->id, 'created_by' => $owner->id,
                'created_at' => '2026-05-03 13:00:00', 'updated_at' => '2026-05-03 13:00:00',
            ]);
        }

        $this->actingAs($this->admin(), 'sanctum');
        $this->getJson('/api/v1/admin/reports/tables/tasks?from=2026-05-01&to=2026-05-07&user_id='.$owner->id.'&search=Report%20Task&sort=title&direction=asc&page=2&per_page=10')
            ->assertOk()->assertJsonPath('data.meta.current_page', 2)->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 12)->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Report Task 11');
        $this->getJson('/api/v1/admin/reports/tables/tasks?sort=name')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson('/api/v1/admin/reports/tables/tasks?per_page=100')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/admin/reports/tables/tasks?search=No%20such%20task')
            ->assertOk()->assertJsonPath('data.meta.total', 0)->assertJsonCount(0, 'data.data');
    }
}
