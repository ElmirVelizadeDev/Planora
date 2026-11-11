<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminProjectManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(Schema::hasTable('projects'), 'Run the project migration before the feature tests.');
    }

    private function admin(): User { return User::factory()->create(['role' => 'admin', 'status' => 'active']); }

    public function test_project_endpoints_require_an_active_admin(): void
    {
        $this->getJson('/api/v1/admin/projects')->assertUnauthorized();
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/projects')->assertForbidden();
    }

    public function test_admin_can_search_filter_paginate_and_read_real_statistics(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        Project::create(['name' => 'Website refresh', 'status' => 'active', 'owner_id' => $owner->id]);
        Project::create(['name' => 'Archive plan', 'status' => 'completed']);
        Project::create(['name' => 'Studio move', 'status' => 'on_hold']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/projects?search='.$owner->name.'&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.statistics.total', 3)
            ->assertJsonPath('meta.statistics.active', 1)->assertJsonPath('data.0.owner.id', $owner->id);
        $this->getJson('/api/v1/admin/projects?status=completed&page=1')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.status', 'completed');
    }

    public function test_admin_can_create_update_and_delete_projects_with_validation(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $this->actingAs($admin, 'sanctum');
        $created = $this->postJson('/api/v1/admin/projects', ['name' => 'Launch', 'status' => 'planned', 'owner_id' => $owner->id, 'start_date' => '2026-11-01', 'due_date' => '2026-11-30'])
            ->assertCreated()->assertJsonPath('project.name', 'Launch')->assertJsonPath('project.owner.id', $owner->id);
        $id = $created->json('project.id');
        $this->patchJson('/api/v1/admin/projects/'.$id, ['status' => 'active', 'name' => 'Launch phase 1'])
            ->assertOk()->assertJsonPath('project.status', 'active')->assertJsonPath('project.name', 'Launch phase 1');
        $this->postJson('/api/v1/admin/projects', ['name' => '', 'status' => 'blocked', 'due_date' => '2026-01-01', 'start_date' => '2026-02-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'status', 'due_date']);
        $this->deleteJson('/api/v1/admin/projects/'.$id)->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $id]);
    }
}
