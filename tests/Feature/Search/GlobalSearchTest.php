<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_global_search_requires_an_active_authenticated_account(): void
    {
        $this->getJson('/api/v1/search?q=plan')->assertUnauthorized();

        $inactive = User::factory()->create(['role' => 'user', 'status' => 'inactive']);
        $this->actingAs($inactive, 'sanctum')->getJson('/api/v1/search?q=plan')->assertForbidden();
    }

    public function test_admin_searches_supported_entities_and_returns_only_minimal_navigation_results(): void
    {
        $searchToken = 'search'.Str::lower(Str::random(12));
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $user = User::factory()->create(['name' => $searchToken.' Member', 'email' => $searchToken.'@example.test']);
        $category = Category::create(['name' => $searchToken.' Work', 'description' => 'Planning category']);
        $project = Project::create(['name' => $searchToken.' Launch', 'description' => 'Launch plan', 'owner_id' => $user->id]);
        $task = Task::create(['title' => $searchToken.' review', 'description' => 'Review the launch', 'owner_id' => $user->id, 'created_by' => $admin->id, 'project_id' => $project->id]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/search?q='.$searchToken)
            ->assertOk()->assertJsonPath('data.total', 4);

        $this->assertEqualsCanonicalizing(['users', 'tasks', 'projects', 'categories'], collect($response->json('data.groups'))->pluck('type')->all());
        $response->assertJsonPath('data.groups.0.results.0.type', 'user')
            ->assertJsonPath('data.groups.0.results.0.to.name', 'admin.users.index')
            ->assertJsonMissingPath('data.groups.0.results.0.email')
            ->assertJsonPath('data.groups.1.results.0.to.name', 'admin.tasks.show')
            ->assertJsonPath('data.groups.2.results.0.to.name', 'admin.projects.show')
            ->assertJsonPath('data.groups.3.results.0.to.name', 'admin.categories.show');

        $this->getJson('/api/v1/search?q='.urlencode($searchToken.'@example.test'))->assertOk()
            ->assertJsonPath('data.groups.0.results.0.title', $searchToken.' Member');
    }

    public function test_moderator_search_observes_each_management_permission_and_never_lists_admins(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator', 'status' => 'active', 'moderator_permissions' => ['users.view', 'tasks.view']]);
        User::factory()->create(['name' => 'Planora Administrator', 'email' => 'admin-planora@planora.test', 'role' => 'admin']);
        User::factory()->create(['name' => 'Planora Member', 'email' => 'member-planora@planora.test', 'role' => 'user']);
        Task::create(['title' => 'Planora task', 'owner_id' => $moderator->id]);
        Project::create(['name' => 'Planora project']);
        Category::create(['name' => 'Planora category']);

        $response = $this->actingAs($moderator, 'sanctum')->getJson('/api/v1/search?q=planora')->assertOk();

        $this->assertEqualsCanonicalizing(['users', 'tasks'], collect($response->json('data.groups'))->pluck('type')->all());
        $response->assertJsonMissing(['title' => 'Planora Administrator'])
            ->assertJsonPath('data.groups.0.results.0.title', 'Planora Member');
    }

    public function test_user_search_is_scoped_to_owned_or_assigned_tasks_and_owned_projects(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $other = User::factory()->create();
        Task::create(['title' => 'Planora owned task', 'owner_id' => $user->id]);
        Task::create(['title' => 'Planora assigned task', 'owner_id' => $other->id, 'assigned_user_id' => $user->id]);
        Task::create(['title' => 'Planora private task', 'owner_id' => $other->id]);
        $deleted = Task::create(['title' => 'Planora deleted task', 'owner_id' => $user->id]);
        $deleted->delete();
        Project::create(['name' => 'Planora owned project', 'owner_id' => $user->id]);
        Project::create(['name' => 'Planora private project', 'owner_id' => $other->id]);
        Category::create(['name' => 'Planora catalog category']);
        User::factory()->create(['name' => 'Planora unrelated user', 'email' => 'private@planora.test']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/search?q=planora')->assertOk();
        $this->assertEqualsCanonicalizing(['tasks', 'projects', 'categories'], collect($response->json('data.groups'))->pluck('type')->all());
        $titles = collect($response->json('data.groups'))->flatMap(fn ($group) => collect($group['results'])->pluck('title'));
        $this->assertEqualsCanonicalizing(['Planora owned task', 'Planora assigned task', 'Planora owned project', 'Planora catalog category'], $titles->all());
        $response->assertJsonMissing(['title' => 'Planora private task'])
            ->assertJsonMissing(['title' => 'Planora private project'])
            ->assertJsonMissing(['title' => 'Planora deleted task'])
            ->assertJsonMissing(['title' => 'Planora unrelated user']);
    }

    public function test_search_handles_empty_short_queries_limits_and_validation(): void
    {
        $searchToken = 'limit'.Str::lower(Str::random(12));
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        foreach (range(1, 9) as $index) {
            Task::create(['title' => $searchToken.' task '.$index]);
        }
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/v1/search')->assertOk()->assertJsonPath('data.total', 0)->assertJsonCount(0, 'data.groups');
        $this->getJson('/api/v1/search?q=%20%20')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/search?q=p')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/search?q=not-a-match')->assertOk()->assertJsonPath('data.total', 0)->assertJsonCount(0, 'data.groups');
        $this->getJson('/api/v1/search?q='.$searchToken.'&limit=2')->assertOk()->assertJsonPath('data.total', 2)->assertJsonCount(2, 'data.groups.0.results');
        $this->getJson('/api/v1/search?q='.str_repeat('x', 121))->assertUnprocessable()->assertJsonValidationErrors(['q']);
        $this->getJson('/api/v1/search?q='.$searchToken.'&limit=9')->assertUnprocessable()->assertJsonValidationErrors(['limit']);
    }

    public function test_search_matches_case_insensitively_under_the_application_database_collation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Task::create(['title' => 'Quarterly LAUNCH plan']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/search?q=launch')->assertOk()
            ->assertJsonPath('data.groups.0.results.0.title', 'Quarterly LAUNCH plan');
    }
}
