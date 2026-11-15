<?php

namespace Tests\Feature\User\Profile;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CurrentUserProfileTest extends TestCase
{
    use DatabaseTransactions;

    private function headers(): array
    {
        return ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
    }

    public function test_profile_page_is_shared_by_all_roles_including_moderators_without_management_permissions(): void
    {
        $this->withoutVite();

        foreach (['user', 'moderator', 'admin'] as $role) {
            $account = User::factory()->create(['role' => $role, 'moderator_permissions' => []]);

            $this->actingAs($account, 'web')->withHeaders($this->headers())
                ->get('/profile')
                ->assertOk()
                ->assertSee('Planora');
        }
    }

    public function test_account_settings_page_is_available_to_every_authenticated_workspace_role(): void
    {
        $this->withoutVite();

        foreach (['user', 'moderator', 'admin'] as $role) {
            $account = User::factory()->create(['role' => $role, 'moderator_permissions' => []]);

            $this->actingAs($account, 'web')->withHeaders($this->headers())
                ->get('/account/settings')
                ->assertOk()
                ->assertSee('Planora');
        }
    }

    public function test_current_user_resource_exposes_own_profile_fields_for_every_role(): void
    {
        foreach (['user', 'moderator', 'admin'] as $role) {
            $account = User::factory()->create([
                'role' => $role,
                'first_name' => 'Taylor',
                'last_name' => 'Jordan',
                'phone' => '+1 555 010 2040',
            ]);

            $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
                ->getJson('/api/v1/user')
                ->assertOk()
                ->assertJsonPath('first_name', 'Taylor')
                ->assertJsonPath('last_name', 'Jordan')
                ->assertJsonPath('phone', '+1 555 010 2040')
                ->assertJsonPath('role', $role)
                ->assertJsonPath('status', 'active')
                ->assertJsonPath('id', $account->id);
        }
    }

    public function test_profile_overview_returns_real_owned_project_and_owned_or_assigned_task_counts_for_each_role(): void
    {
        foreach (['user', 'moderator', 'admin'] as $role) {
            $account = User::factory()->create(['role' => $role, 'status' => 'active']);
            $other = User::factory()->create(['role' => 'user', 'status' => 'active']);

            Project::query()->create(['name' => 'Account project', 'status' => 'active', 'owner_id' => $account->id]);
            Project::query()->create(['name' => 'Completed account project', 'status' => 'completed', 'owner_id' => $account->id]);
            Project::query()->create(['name' => 'Other account project', 'status' => 'completed', 'owner_id' => $other->id]);

            Task::query()->create(['title' => 'Owned pending task', 'status' => 'pending', 'owner_id' => $account->id, 'created_by' => $account->id]);
            Task::query()->create(['title' => 'Assigned completed task', 'status' => 'completed', 'owner_id' => $other->id, 'assigned_user_id' => $account->id, 'created_by' => $other->id]);
            Task::query()->create(['title' => 'Owned and assigned task', 'status' => 'in_progress', 'owner_id' => $account->id, 'assigned_user_id' => $account->id, 'created_by' => $account->id]);
            Task::query()->create(['title' => 'Other account task', 'status' => 'pending', 'owner_id' => $other->id, 'created_by' => $other->id]);
            $deleted = Task::query()->create(['title' => 'Deleted account task', 'status' => 'pending', 'owner_id' => $account->id, 'created_by' => $account->id]);
            $deleted->delete();

            $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
                ->getJson('/api/v1/user/overview?user_id='.$other->id)
                ->assertOk()
                ->assertJsonPath('data.projects.total', 2)
                ->assertJsonPath('data.projects.completed', 1)
                ->assertJsonPath('data.tasks.total', 3)
                ->assertJsonPath('data.tasks.completed', 1)
                ->assertJsonPath('data.tasks.pending', 1);
        }
    }

    public function test_user_can_update_only_their_own_personal_profile_fields(): void
    {
        $account = User::factory()->create([
            'first_name' => 'Taylor',
            'last_name' => 'Jordan',
            'phone' => null,
        ]);
        $other = User::factory()->create(['name' => 'Other Person']);

        $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/user/profile', [
                'first_name' => 'Robin',
                'last_name' => 'Nguyen',
                'phone' => '+1 555 010 2040',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Robin Nguyen')
            ->assertJsonPath('user.first_name', 'Robin')
            ->assertJsonPath('user.last_name', 'Nguyen')
            ->assertJsonPath('user.phone', '+1 555 010 2040')
            ->assertJsonMissingPath('user.password');

        $this->assertSame('Other Person', $other->fresh()->name);
        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $account->id,
            'actor_id' => $account->id,
            'event' => 'user.profile_updated',
        ]);
    }

    public function test_profile_update_rejects_privileged_and_unsupported_fields_and_invalid_names(): void
    {
        $account = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => ['users.view']]);

        $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/user/profile', [
                'first_name' => 'Morgan',
                'email' => 'changed@example.com',
                'role' => 'admin',
                'status' => 'banned',
                'moderator_permissions' => ['users.status.manage'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'role', 'status', 'moderator_permissions']);

        $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/user/profile', ['first_name' => 'R', 'last_name' => 'A'.str_repeat('b', 51)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name']);

        $this->assertSame('moderator', $account->fresh()->role);
        $this->assertSame('active', $account->fresh()->status);
        $this->assertSame(['users.view'], $account->fresh()->moderator_permissions);
    }

    public function test_user_can_upload_replace_and_remove_only_their_managed_avatar(): void
    {
        Storage::fake('public');
        $account = User::factory()->create();

        $first = $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/user/avatar', ['avatar' => UploadedFile::fake()->image('first.png', 300, 300)])
            ->assertOk()
            ->json('user.avatar');
        $firstPath = str_replace('/storage/', '', parse_url($first, PHP_URL_PATH));
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/user/avatar', ['avatar' => UploadedFile::fake()->image('second.jpg', 320, 320)])
            ->assertOk()
            ->json('user.avatar');
        $secondPath = str_replace('/storage/', '', parse_url($second, PHP_URL_PATH));
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->deleteJson('/api/v1/user/avatar')
            ->assertOk()
            ->assertJsonPath('user.avatar', null);
        Storage::disk('public')->assertMissing($secondPath);
    }

    public function test_avatar_upload_rejects_unsupported_and_oversized_images(): void
    {
        $account = User::factory()->create();

        $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/user/avatar', ['avatar' => UploadedFile::fake()->create('notes.txt', 12, 'text/plain')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/user/avatar', ['avatar' => UploadedFile::fake()->image('large.png')->size(2049)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_password_change_checks_current_password_policy_and_revokes_tokens(): void
    {
        $account = User::factory()->create(['password' => Hash::make('CurrentPassword12')]);
        $token = $account->createToken('profile-test');

        $this->actingAs($account, 'sanctum')->withSession(['password_hash_web' => $account->getAuthPassword()])->withHeaders($this->headers())
            ->putJson('/api/v1/user/password', [
                'current_password' => 'CurrentPassword12',
                'password' => 'NewSecurePassword34',
                'password_confirmation' => 'NewSecurePassword34',
            ])
            ->assertOk()
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('current_password');

        $this->assertTrue(Hash::check('NewSecurePassword34', $account->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $account->id,
            'event' => 'auth.password_changed',
        ]);
        $this->getJson('/api/v1/user')->assertOk();
    }

    public function test_password_change_rejects_wrong_current_password_mismatch_and_weak_values(): void
    {
        $account = User::factory()->create(['password' => Hash::make('CurrentPassword12')]);

        $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->putJson('/api/v1/user/password', [
                'current_password' => 'incorrect',
                'password' => 'NewSecurePassword34',
                'password_confirmation' => 'NewSecurePassword34',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->actingAs($account, 'sanctum')->withHeaders($this->headers())
            ->putJson('/api/v1/user/password', [
                'current_password' => 'CurrentPassword12',
                'password' => 'short',
                'password_confirmation' => 'not-the-same',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('CurrentPassword12', $account->fresh()->password));
    }

    public function test_profile_mutations_require_authentication(): void
    {
        $this->withHeaders($this->headers())->getJson('/api/v1/user')->assertUnauthorized();
        $this->withHeaders($this->headers())->getJson('/api/v1/user/overview')->assertUnauthorized();
        $this->withHeaders($this->headers())->patchJson('/api/v1/user/profile', ['first_name' => 'Taylor'])->assertUnauthorized();
        $this->withHeaders($this->headers())->postJson('/api/v1/user/avatar')->assertUnauthorized();
        $this->withHeaders($this->headers())->deleteJson('/api/v1/user/avatar')->assertUnauthorized();
        $this->withHeaders($this->headers())->putJson('/api/v1/user/password')->assertUnauthorized();
    }
}
