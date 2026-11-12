<?php

namespace Tests\Feature\Admin\Categories;

use App\Models\Category;
use App\Models\User;
use App\Services\Api\V1\Admin\CategoryManagementService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminCategoryManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('categories'), 'Run the categories migration before the feature tests.');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_category_endpoints_require_an_active_admin(): void
    {
        $this->getJson('/api/v1/admin/categories')->assertUnauthorized();
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/categories')->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/categories', ['name' => 'Private'])->assertForbidden();
    }

    public function test_admin_can_list_search_filter_sort_and_paginate_categories(): void
    {
        $admin = $this->admin();
        Category::factory()->create(['name' => 'Product', 'description' => 'Planning and delivery']);
        Category::factory()->create(['name' => 'Design', 'description' => 'Experience design']);
        Category::factory()->create(['name' => 'Research', 'description' => 'Experiments'])->delete();

        $this->actingAs($admin, 'sanctum');
        $this->getJson('/api/v1/admin/categories?search=Planning&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Product')
            ->assertJsonPath('meta.statistics.total', 3)->assertJsonPath('meta.statistics.active', 2)
            ->assertJsonPath('meta.statistics.archived', 1)->assertJsonPath('meta.statistics.created_this_month', 2);
        $this->getJson('/api/v1/admin/categories?status=archived')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.status', 'archived');
        $this->getJson('/api/v1/admin/categories?status=all&sort=name&direction=desc&page=2&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('meta.current_page', 2)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Product');
        $this->getJson('/api/v1/admin/categories?status=unknown')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_admin_can_view_create_update_archive_and_restore_categories(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum');
        $created = $this->postJson('/api/v1/admin/categories', ['name' => '  Product  ', 'description' => 'Product work', 'color' => '#123ABC'])
            ->assertCreated()->assertJsonPath('category.name', 'Product')->assertJsonPath('category.status', 'active');
        $id = $created->json('category.id');

        $this->getJson('/api/v1/admin/categories/'.$id)->assertOk()->assertJsonPath('category.color', '#123ABC');
        $this->patchJson('/api/v1/admin/categories/'.$id, ['name' => 'Product Design', 'description' => null])
            ->assertOk()->assertJsonPath('category.name', 'Product Design')->assertJsonPath('category.description', null);
        $this->deleteJson('/api/v1/admin/categories/'.$id)->assertOk();
        $this->assertSoftDeleted('categories', ['id' => $id]);
        $this->getJson('/api/v1/admin/categories/'.$id)->assertOk()->assertJsonPath('category.status', 'archived');
        $this->postJson('/api/v1/admin/categories/'.$id.'/restore')->assertOk()->assertJsonPath('category.status', 'active');
        $this->assertDatabaseHas('categories', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_duplicate_names_and_invalid_input_are_rejected(): void
    {
        $admin = $this->admin();
        Category::factory()->create(['name' => 'Design']);
        $archived = Category::factory()->create(['name' => 'Archived name']);
        $archived->delete();
        $this->actingAs($admin, 'sanctum');
        $this->postJson('/api/v1/admin/categories', ['name' => ' design '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/admin/categories', ['name' => ' archived name '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->patchJson('/api/v1/admin/categories/'.$archived->id, ['name' => 'Renamed'])->assertNotFound();
        $active = Category::query()->where('name', 'Design')->firstOrFail();
        $this->patchJson('/api/v1/admin/categories/'.$active->id, ['name' => 'ARCHIVED NAME'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/admin/categories', ['name' => '', 'color' => 'blue'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'color']);
        $this->postJson('/api/v1/admin/categories', ['name' => 'New category', 'color' => '#12345'])->assertUnprocessable()->assertJsonValidationErrors('color');
    }

    public function test_category_seeder_is_local_only_idempotent_and_preserves_existing_and_archived_data(): void
    {
        $existing = Category::factory()->create(['name' => 'development', 'description' => 'Keep this description.', 'color' => '#123456']);
        $archived = Category::factory()->create(['name' => 'DESIGN', 'description' => 'Archived user data.']);
        $archived->delete();

        $this->assertSame('testing', app()->environment());
        try {
            $this->seed(CategorySeeder::class);
            $this->fail('The CategorySeeder must refuse to run outside local.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('restricted to the local environment', $exception->getMessage());
        }

        app()->instance('env', 'local');
        try {
            $this->seed(CategorySeeder::class);
            $this->seed(CategorySeeder::class);
        } finally {
            app()->instance('env', 'testing');
        }

        foreach (['Web Development', 'UI/UX Design', 'Mobile Development', 'Database', 'Marketing', 'Research', 'Bug Fixes', 'Documentation', 'DevOps', 'Content Creation'] as $name) {
            $this->assertSame(1, Category::withTrashed()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->count(), $name.' should exist only once.' );
        }
        $this->assertDatabaseHas('categories', ['id' => $existing->id, 'name' => 'development', 'description' => 'Keep this description.', 'color' => '#123456']);
        $this->assertSoftDeleted('categories', ['id' => $archived->id]);

        $statistics = app(CategoryManagementService::class)->statistics();
        $this->assertSame(Category::withTrashed()->count(), $statistics['total']);
        $this->assertSame(Category::query()->count(), $statistics['active']);
        $this->assertSame(Category::onlyTrashed()->count(), $statistics['archived']);
        $this->assertSame(
            Category::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            $statistics['created_this_month'],
        );
    }
}
