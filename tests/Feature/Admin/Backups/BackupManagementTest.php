<?php

namespace Tests\Feature\Admin\Backups;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('backups');
        config(['backups.disk' => 'backups', 'backups.path' => 'test-planora-backups']);
        config(['filesystems.disks.backups.driver' => 'local', 'filesystems.disks.backups.visibility' => 'private']);
    }

    public function test_backup_management_is_admin_only_and_exposes_only_safe_metadata(): void
    {
        $this->getJson('/api/v1/admin/backups')->assertUnauthorized();

        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/backups')->assertForbidden();

        $moderator = User::factory()->create(['role' => 'moderator', 'status' => 'active']);
        $this->actingAs($moderator, 'sanctum')->getJson('/api/v1/admin/backups')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/backups')->assertOk()
            ->assertJsonPath('summary.total_backups', 0);
        $this->assertArrayNotHasKey('artifact_key', $response->json());
    }

    public function test_backup_list_validation_rejects_unsupported_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/backups?type=logs')->assertUnprocessable();
        $this->getJson('/api/v1/admin/backups?per_page=500')->assertUnprocessable();
    }

    public function test_restore_and_delete_require_confirmation_matching_the_backup_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $id = (string) \Illuminate\Support\Str::uuid();
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/backups/'.$id.'/restore', ['confirmation' => 'RESTORE another-id'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Confirmation must match RESTORE and the backup identifier.');

        $this->deleteJson('/api/v1/admin/backups/'.$id, ['confirmation' => 'DELETE another-id'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Confirm deletion using the backup identifier.');
    }
}
