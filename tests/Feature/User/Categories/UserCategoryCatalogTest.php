<?php

namespace Tests\Feature\User\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserCategoryCatalogTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('categories'), 'Run the categories migration before the feature tests.');
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'status' => 'active']);
    }

    public function test_authenticated_users_can_list_active_categories_with_a_limited_resource(): void
    {
        $user = $this->user();
        Category::factory()->create(['name' => 'Design', 'description' => 'Visual systems', 'color' => '#336699']);
        $archived = Category::factory()->create(['name' => 'Archived design']);
        $archived->delete();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Design')
            ->assertJsonPath('data.0.description', 'Visual systems')
            ->assertJsonPath('data.0.color', '#336699')
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.deleted_at');
    }

    public function test_catalog_requires_a_user_role_and_users_cannot_access_admin_category_management(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/categories')->assertForbidden();

        $user = $this->user();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/categories')->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/categories', ['name' => 'Forbidden'])->assertForbidden();
    }

    public function test_user_catalog_does_not_register_category_mutation_routes(): void
    {
        $category = Category::factory()->create(['name' => 'Read only']);
        $this->actingAs($this->user(), 'sanctum');

        $this->postJson('/api/v1/categories', ['name' => 'Forbidden'])->assertStatus(405);
        $this->patchJson('/api/v1/categories/'.$category->id, ['name' => 'Changed'])->assertStatus(405);
        $this->deleteJson('/api/v1/categories/'.$category->id)->assertStatus(405);
        $this->postJson('/api/v1/categories/'.$category->id.'/restore')->assertNotFound();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Read only', 'deleted_at' => null]);
    }

    public function test_archived_categories_are_hidden_from_both_listing_and_direct_details(): void
    {
        $active = Category::factory()->create(['name' => 'Active option']);
        $archived = Category::factory()->create(['name' => 'Archived option']);
        $archived->delete();

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/v1/categories')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['name' => 'Archived option']);

        $this->getJson('/api/v1/categories/'.$archived->id)->assertNotFound();
        $this->getJson('/api/v1/categories/'.$active->id)->assertOk()->assertJsonPath('category.name', 'Active option');
    }

    public function test_search_sorting_and_pagination_are_applied_by_the_catalog_endpoint(): void
    {
        $user = $this->user();
        Category::factory()->create(['name' => 'Research', 'description' => 'Discover user needs']);
        Category::factory()->create(['name' => 'Design']);
        Category::factory()->create(['name' => 'Development']);
        Category::factory()->create(['name' => 'Maintenance']);

        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/v1/categories?search=needs')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Research');
        $this->getJson('/api/v1/categories?sort=name&direction=desc&page=2&per_page=2')
            ->assertOk()->assertJsonPath('meta.total', 4)->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Development');
        $this->getJson('/api/v1/categories?sort=status')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }
}
