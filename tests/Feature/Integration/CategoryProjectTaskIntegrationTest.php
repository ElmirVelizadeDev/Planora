<?php

namespace Tests\Feature\Integration;

use App\Models\Category;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProjectDemoSeeder;
use Database\Seeders\UserTasksDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryProjectTaskIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('planora_security_test', config('database.connections.mysql.database'));
        $this->assertTrue(Schema::hasColumn('projects', 'category_id'));
        $this->assertTrue(Schema::hasColumn('tasks', 'category_id'));
    }

    private function user(string $name = 'Test User'): User
    {
        return User::factory()->create(['name' => $name, 'role' => 'user', 'status' => 'active']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_admin_can_assign_and_filter_active_categories_on_projects_and_tasks(): void
    {
        $admin = $this->admin();
        $owner = $this->user();
        $category = Category::factory()->create(['name' => 'Development']);
        $archived = Category::factory()->create(['name' => 'Old work']);
        $archived->delete();
        $this->actingAs($admin, 'sanctum');

        $project = $this->postJson('/api/v1/admin/projects', [
            'name' => 'Integration project', 'status' => 'active', 'owner_id' => $owner->id, 'category_id' => $category->id,
        ])->assertCreated()->assertJsonPath('project.category.id', $category->id)->json('project');

        $this->postJson('/api/v1/admin/projects', [
            'name' => 'Invalid project', 'status' => 'planned', 'owner_id' => $owner->id, 'category_id' => $archived->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('category_id');

        $this->getJson('/api/v1/admin/projects?category_id='.$category->id)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.category.name', 'Development');
        $task = $this->postJson('/api/v1/admin/tasks', [
            'title' => 'Integration task', 'owner_id' => $owner->id, 'assigned_user_id' => $owner->id,
            'project_id' => $project['id'], 'category_id' => $category->id,
        ])->assertCreated()->assertJsonPath('task.category.id', $category->id)->json('task');
        $this->postJson('/api/v1/admin/tasks', [
            'title' => 'Archived category task', 'owner_id' => $owner->id, 'category_id' => $archived->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->getJson('/api/v1/admin/tasks?category_id='.$category->id)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.category.name', 'Development');
        $this->getJson('/api/v1/admin/categories?status=active')
            ->assertOk()->assertJsonPath('data.0.projects_count', 1)->assertJsonPath('data.0.tasks_count', 1);
        $this->getJson('/api/v1/admin/categories/'.$category->id)
            ->assertOk()->assertJsonPath('category.projects_count', 1)->assertJsonPath('category.tasks_count', 1)
            ->assertJsonFragment(['name' => 'Integration project'])->assertJsonFragment(['title' => 'Integration task']);

        $this->deleteJson('/api/v1/admin/categories/'.$category->id)->assertOk();
        $this->patchJson('/api/v1/admin/projects/'.$project['id'], ['category_id' => $category->id])
            ->assertOk()->assertJsonPath('project.category.status', 'archived');
        $this->patchJson('/api/v1/admin/tasks/'.$task['id'], ['category_id' => $category->id])
            ->assertOk()->assertJsonPath('task.category.status', 'archived');
    }

    public function test_users_see_categories_only_within_their_existing_project_and_task_access(): void
    {
        $user = $this->user();
        $other = $this->user('Other User');
        $category = Category::factory()->create(['name' => 'Visible work']);
        $archived = Category::factory()->create(['name' => 'Archived work']);
        $archived->delete();

        $ownProject = Project::query()->create(['name' => 'Owned project', 'status' => 'active', 'owner_id' => $user->id, 'category_id' => $category->id]);
        Project::query()->create(['name' => 'Private project', 'status' => 'active', 'owner_id' => $other->id, 'category_id' => $category->id]);
        $ownedTask = Task::query()->create(['title' => 'Owned task', 'owner_id' => $user->id, 'created_by' => $user->id, 'project_id' => $ownProject->id, 'category_id' => $category->id]);
        Task::query()->create(['title' => 'Private task', 'owner_id' => $other->id, 'created_by' => $other->id, 'category_id' => $category->id]);
        Task::query()->create(['title' => 'Assigned task', 'owner_id' => $other->id, 'assigned_user_id' => $user->id, 'created_by' => $other->id, 'project_id' => $ownProject->id, 'category_id' => $category->id]);

        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/v1/projects?category_id='.$category->id)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ownProject->id)
            ->assertJsonPath('data.0.category.name', 'Visible work');
        $this->getJson('/api/v1/tasks?category_id='.$category->id)
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['title' => $ownedTask->title])->assertJsonFragment(['title' => 'Assigned task']);
        $this->getJson('/api/v1/projects/'.$ownProject->id.'/tasks?category_id='.$category->id)
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonFragment(['title' => 'Assigned task']);

        $this->getJson('/api/v1/categories/'.$category->id)
            ->assertOk()->assertJsonPath('category.projects_count', 1)->assertJsonPath('category.tasks_count', 2)
            ->assertJsonFragment(['name' => 'Owned project'])->assertJsonMissing(['name' => 'Private project'])
            ->assertJsonFragment(['title' => 'Assigned task'])->assertJsonMissing(['title' => 'Private task']);
        $this->getJson('/api/v1/categories/'.$archived->id)->assertNotFound();
        $this->postJson('/api/v1/tasks', ['title' => 'New task', 'category_id' => $archived->id])->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_archived_categories_keep_existing_associations_and_cannot_be_force_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Historical']);
        $owner = $this->user();
        $project = Project::query()->create(['name' => 'Historical project', 'status' => 'completed', 'owner_id' => $owner->id, 'category_id' => $category->id]);
        $task = Task::query()->create(['title' => 'Historical task', 'owner_id' => $owner->id, 'created_by' => $owner->id, 'category_id' => $category->id]);

        $category->delete();
        $this->assertSame($category->id, $project->fresh()->category->id);
        $this->assertSame($category->id, $task->fresh()->category->id);
        $this->expectException(\Illuminate\Database\QueryException::class);
        $category->forceDelete();
    }

    public function test_local_demo_seeders_assign_only_existing_active_categories(): void
    {
        foreach (['Web Development', 'UI/UX Design', 'Mobile Development', 'Database', 'Marketing', 'Research', 'Bug Fixes', 'Documentation', 'DevOps', 'Content Creation'] as $name) {
            Category::factory()->create(['name' => $name]);
        }
        $user = $this->user();

        app()->instance('env', 'local');
        try {
            $this->seed(ProjectDemoSeeder::class);
            $this->seed(UserTasksDemoSeeder::class);
        } finally {
            app()->instance('env', 'testing');
        }

        $this->assertSame(4, Project::query()->where('owner_id', $user->id)->whereNotNull('category_id')->count());
        $this->assertSame(21, Task::query()->where('owner_id', $user->id)->whereNotNull('category_id')->count());
        $this->assertSame(0, Project::query()->whereNotNull('category_id')->whereDoesntHave('category')->count());
        $this->assertSame(0, Task::query()->whereNotNull('category_id')->whereDoesntHave('category')->count());

        $projects = Project::query()->where('owner_id', $user->id)->with('category', 'tasks.category')->get();
        $this->assertSame(['Web Development', 'UI/UX Design', 'Database', 'Marketing'], $projects->pluck('category.name')->all());
        foreach ($projects as $project) {
            $this->assertTrue($project->tasks->every(fn (Task $task): bool => $task->category_id === $project->category_id));
        }
        $this->assertSame(16, Task::query()->where('owner_id', $user->id)->whereNotNull('project_id')->count());
    }

    public function test_category_seeder_is_idempotent_and_preserves_existing_category_data(): void
    {
        $existing = Category::factory()->create([
            'name' => 'Web Development',
            'description' => 'A category maintained by the local team.',
            'color' => '#123456',
        ]);

        app()->instance('env', 'local');
        try {
            $this->seed(CategorySeeder::class);
            $this->seed(CategorySeeder::class);
        } finally {
            app()->instance('env', 'testing');
        }

        $this->assertSame('A category maintained by the local team.', $existing->fresh()->description);
        $this->assertSame('#123456', $existing->fresh()->color);
        $this->assertSame(1, Category::withTrashed()->whereRaw('LOWER(name) = ?', ['web development'])->count());
        $this->assertSame(10, Category::query()->count());
        $this->assertSame(10, Category::query()->distinct()->count('color'));
        $this->assertSame('#8B5CF6', Category::query()->where('name', 'UI/UX Design')->value('color'));
    }

    public function test_demo_seeders_refresh_legacy_marked_rows_without_creating_duplicates(): void
    {
        $category = Category::factory()->create(['name' => 'Web Development']);
        $user = $this->user();
        $oldProjectNames = [
            'Customer onboarding refresh',
            'Quarterly research library',
            'Workspace accessibility review',
            'Spring release coordination',
        ];
        $oldProjectTaskNames = [
            'Confirm scope and success measures',
            'Prepare the first delivery milestone',
            'Review stakeholder feedback',
            'Publish the project handoff notes',
        ];
        $projectIds = [];
        $demoTaskIds = [];

        foreach ($oldProjectNames as $projectIndex => $oldName) {
            $project = Project::query()->create([
                'name' => sprintf('[Planora Seed U%d P%02d] %s', $user->id, $projectIndex + 1, $oldName),
                'description' => 'Previous demo description.', 'status' => 'active',
                'owner_id' => $user->id, 'category_id' => $category->id,
            ]);
            $projectIds[] = $project->id;

            foreach ($oldProjectTaskNames as $taskIndex => $oldTitle) {
                $task = Task::query()->create([
                    'title' => sprintf('[Planora Seed P%d T%02d] %s', $project->id, $taskIndex + 1, $oldTitle),
                    'project_id' => $project->id, 'owner_id' => $user->id,
                    'assigned_user_id' => $user->id, 'created_by' => $user->id,
                    'category_id' => $category->id,
                ]);
                $demoTaskIds[] = $task->id;
            }
        }

        $oldStandaloneNames = [
            'Review the weekly project roadmap',
            'Prepare client handoff notes',
            'Close out sprint action items',
            'Confirm delivery milestones',
            'Organize research and reference files',
        ];
        foreach ($oldStandaloneNames as $index => $oldTitle) {
            $task = Task::query()->create([
                'title' => sprintf('[Planora Seed U%d T%02d] %s', $user->id, $index + 1, $oldTitle),
                'owner_id' => $user->id, 'created_by' => $user->id,
            ]);
            $demoTaskIds[] = $task->id;
        }

        app()->instance('env', 'local');
        try {
            $this->seed(ProjectDemoSeeder::class);
            $this->seed(UserTasksDemoSeeder::class);
            $this->seed(ProjectDemoSeeder::class);
            $this->seed(UserTasksDemoSeeder::class);
        } finally {
            app()->instance('env', 'testing');
        }

        $projects = Project::query()->where('owner_id', $user->id)->get();
        $this->assertCount(4, $projects);
        $this->assertSame($projectIds, $projects->pluck('id')->all());
        $this->assertSame(16, Task::query()->where('owner_id', $user->id)->whereNotNull('project_id')->count());
        $demoTasks = Task::query()->where('owner_id', $user->id)->where(function ($query) use ($user) {
            $query->where('title', 'like', '[Planora Seed U'.$user->id.' T%')
                ->orWhere('title', 'like', '[Planora Seed P% T%');
        })->orderBy('id')->get();
        $this->assertCount(21, $demoTasks);
        $this->assertSame($demoTaskIds, $demoTasks->pluck('id')->all());
        $this->assertSame([
            sprintf('[Planora Seed U%d P01] Company Website', $user->id),
            sprintf('[Planora Seed U%d P02] Mobile App Redesign', $user->id),
            sprintf('[Planora Seed U%d P03] Database Optimization', $user->id),
            sprintf('[Planora Seed U%d P04] Product Launch', $user->id),
        ], $projects->pluck('name')->all());
        $this->assertSame($category->id, $projects->first()->category_id);
        $this->assertSame($category->id, $demoTasks->first()->category_id);
    }

    public function test_rerunning_local_demo_seeders_preserves_archived_category_associations(): void
    {
        $categories = collect(['Web Development', 'UI/UX Design', 'Mobile Development', 'Database', 'Marketing', 'Research', 'Bug Fixes', 'Documentation', 'DevOps', 'Content Creation'])
            ->mapWithKeys(fn (string $name) => [$name => Category::factory()->create(['name' => $name])]);
        $user = $this->user();

        app()->instance('env', 'local');
        try {
            $this->seed(ProjectDemoSeeder::class);
            $this->seed(UserTasksDemoSeeder::class);

            $projectIds = Project::query()->where('owner_id', $user->id)->pluck('category_id', 'name');
            $taskIds = Task::withTrashed()->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->where(fn ($tasks) => $tasks->where('title', 'like', '[Planora Seed U'.$user->id.' T%')
                        ->orWhere('title', 'like', '[Planora Seed P% T%'));
            })->pluck('category_id', 'title');

            $this->assertCount(4, $projectIds);
            $this->assertCount(21, $taskIds);
            $categories->each->delete();

            $this->seed(ProjectDemoSeeder::class);
            $this->seed(UserTasksDemoSeeder::class);

            $this->assertSame($projectIds->all(), Project::query()->where('owner_id', $user->id)->pluck('category_id', 'name')->all());
            $this->assertSame($taskIds->all(), Task::withTrashed()->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->where(fn ($tasks) => $tasks->where('title', 'like', '[Planora Seed U'.$user->id.' T%')
                        ->orWhere('title', 'like', '[Planora Seed P% T%'));
            })->pluck('category_id', 'title')->all());
        } finally {
            app()->instance('env', 'testing');
        }
    }
}
