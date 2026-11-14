<?php

namespace Tests\Feature\Admin\Users;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function headers(): array
    {
        return ['Origin' => 'http://planora.test', 'Referer' => 'http://planora.test/'];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_initial_schema_has_only_supported_roles_and_statuses(): void
    {
        $role = \DB::selectOne("SHOW COLUMNS FROM users WHERE Field = 'role'")->Type;
        $status = \DB::selectOne("SHOW COLUMNS FROM users WHERE Field = 'status'")->Type;

        $this->assertSame("enum('user','moderator','admin')", $role);
        $this->assertSame("enum('active','inactive','suspended','banned')", $status);
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('user_activity_logs'));
    }

    public function test_user_index_returns_database_aggregated_statistics(): void
    {
        $admin = $this->admin();
        User::factory()->create(['role' => 'user', 'status' => 'inactive']);
        User::factory()->create(['role' => 'moderator', 'status' => 'suspended']);
        User::factory()->create(['role' => 'user', 'status' => 'banned']);

        $expected = [
            'total_users' => User::query()->count(),
            'active_users' => User::query()->where('status', 'active')->count(),
            'inactive_users' => User::query()->where('status', 'inactive')->count(),
            'suspended_users' => User::query()->where('status', 'suspended')->count(),
            'banned_users' => User::query()->where('status', 'banned')->count(),
        ];

        $response = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users')
            ->assertOk();

        foreach ($expected as $key => $value) {
            $response->assertJsonPath('meta.statistics.'.$key, $value);
        }

        $adminCount = User::query()->where('role', 'admin')->count();
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users?role=admin')
            ->assertOk()
            ->assertJsonPath('meta.statistics.total_users', $adminCount);
    }

    public function test_new_users_statistic_counts_only_non_deleted_users_in_the_last_30_days(): void
    {
        $now = \Illuminate\Support\Carbon::parse('2026-11-08 12:00:00', 'UTC');
        \Illuminate\Support\Carbon::setTestNow($now);

        try {
            $admin = $this->admin();
            $baselineNewUsers = app(\App\Services\Api\V1\Admin\UserManagementQueryService::class)
                ->statistics()['new_users_last_30_days'];
            $createUser = static fn (array $attributes) => User::factory()->create($attributes + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $createUser(['role' => 'user', 'status' => 'active', 'created_at' => '2026-10-09 00:00:00']);
            $createUser(['role' => 'user', 'status' => 'inactive', 'created_at' => '2026-11-01 12:00:00']);
            $createUser(['role' => 'user', 'status' => 'active', 'created_at' => $now]);
            $createUser(['role' => 'user', 'status' => 'active', 'created_at' => '2026-10-08 23:59:59']);
            $createUser(['role' => 'admin', 'status' => 'active', 'created_at' => '2026-11-05 12:00:00']);
            $createUser(['role' => 'moderator', 'status' => 'active', 'created_at' => '2026-11-05 12:00:00']);
            $createUser(['role' => 'user', 'status' => 'active', 'created_at' => '2026-11-08 12:00:01']);
            $deletedUser = $createUser(['role' => 'user', 'status' => 'active', 'created_at' => '2026-11-05 12:00:00']);
            $deletedUser->delete();

            $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
                ->getJson('/api/v1/admin/users')
                ->assertOk()
                ->assertJsonPath('meta.statistics.new_users_last_30_days', $baselineNewUsers + 3);

            $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
                ->getJson('/api/v1/admin/users?role=admin')
                ->assertOk()
                ->assertJsonPath('meta.statistics.new_users_last_30_days', 0);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_guests_and_regular_users_cannot_access_user_management_apis(): void
    {
        $this->withHeaders($this->headers())->getJson('/api/v1/admin/users')->assertUnauthorized();

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAs($user, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id.'/role', ['role' => 'admin'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id.'/status', ['status' => 'banned', 'reason' => 'self action'])->assertForbidden();
    }

    public function test_moderator_permissions_are_enforced_per_account(): void
    {
        $target = User::factory()->create(['role' => 'user']);
        $moderator = User::factory()->create([
            'role' => 'moderator',
            'moderator_permissions' => ['users.view', 'activity.view'],
        ]);

        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users')->assertOk();
        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$target->id.'/role', ['role' => 'admin'])->assertForbidden();
        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$target->id.'/status', ['status' => 'banned', 'reason' => 'unauthorized'])->assertForbidden();

        $restricted = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => []]);
        $this->actingAs($restricted, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_admin_can_create_users_with_hashed_password_and_role_and_status_separated(): void
    {
        $admin = $this->admin();
        $email = 'managed-'.Str::uuid().'@gmail.com';

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users', [
                'name' => 'Managed Account',
                'email' => $email,
                'password' => 'Initial-Password-123',
                'password_confirmation' => 'Initial-Password-123',
                'role' => 'user',
                'status' => 'inactive',
            ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.status', 'inactive')
            ->assertJsonMissingPath('user.password');

        $created = User::where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check('Initial-Password-123', $created->password));
        $this->assertSame($admin->id, $created->status_changed_by);
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $created->id, 'actor_id' => $admin->id, 'event' => 'user.created']);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users', [
                'name' => 'Unsafe Account', 'email' => 'unsafe-'.Str::uuid().'@gmail.com',
                'password' => 'Initial-Password-123', 'password_confirmation' => 'Initial-Password-123',
                'role' => 'seller', 'status' => 'active',
            ])->assertUnprocessable();
    }

    public function test_admin_can_create_each_supported_role_and_duplicate_emails_are_rejected(): void
    {
        $admin = $this->admin();

        foreach (['user', 'moderator', 'admin'] as $role) {
            $email = 'managed-'.$role.'-'.Str::uuid().'@gmail.com';
            $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
                ->postJson('/api/v1/admin/users', [
                    'name' => 'Created '.ucfirst($role),
                    'email' => $email,
                    'password' => 'Initial-Password-123',
                    'password_confirmation' => 'Initial-Password-123',
                    'role' => $role,
                    'status' => 'active',
                    'moderator_permissions' => $role === 'moderator' ? ['users.view'] : [],
                ])->assertCreated()->assertJsonPath('user.role', $role)->assertJsonMissingPath('user.password');

            $created = User::where('email', $email)->firstOrFail();
            $this->assertTrue(Hash::check('Initial-Password-123', $created->password));
            $this->assertNull($created->email_verified_at);
            if ($role === 'user') $this->assertNull($created->profile_photo);
            if ($role === 'moderator') $this->assertSame(['users.view'], $created->moderator_permissions);
        }

        $existing = User::where('role', 'user')->firstOrFail();
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users', [
                'name' => 'Duplicate Email', 'email' => $existing->email,
                'password' => 'Initial-Password-123', 'password_confirmation' => 'Initial-Password-123',
                'role' => 'user', 'status' => 'active',
            ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $username = 'reserved-'.Str::uuid();
        User::factory()->create(['username' => $username]);
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users', [
                'name' => 'Duplicate Username', 'email' => 'unique-'.Str::uuid().'@gmail.com', 'username' => $username,
                'password' => 'Initial-Password-123', 'password_confirmation' => 'Initial-Password-123',
                'role' => 'user', 'status' => 'active',
            ])->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_avatar_can_be_uploaded_replaced_and_removed_safely(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $email = 'avatar-'.Str::uuid().'@gmail.com';
        $headers = $this->headers() + ['Accept' => 'application/json'];
        $createPayload = [
            'name' => 'Avatar Example', 'first_name' => 'Avatar', 'last_name' => 'Example',
            'email' => $email, 'username' => 'avatar-'.Str::uuid(), 'phone' => '+1 555 0100',
            'password' => 'Initial-Password-123', 'password_confirmation' => 'Initial-Password-123',
            'role' => 'user', 'status' => 'active',
        ];

        $createResponse = $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->post('/api/v1/admin/users', $createPayload + ['avatar' => UploadedFile::fake()->image('first-avatar.png', 128, 128)])
            ->assertCreated();
        $user = User::where('email', $email)->firstOrFail();
        $firstPath = $user->profile_photo;
        $this->assertStringStartsWith('user-avatars/', $firstPath);
        Storage::disk('public')->assertExists($firstPath);
        $this->assertStringContainsString('/storage/user-avatars/', $createResponse->json('user.avatar'));
        $this->assertNotEmpty($user->username);
        $this->assertSame('+1 555 0100', $user->phone);

        $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->post('/api/v1/admin/users/'.$user->id, [
                '_method' => 'PATCH', 'name' => 'Avatar Updated', 'email' => $email,
                'first_name' => 'Avatar', 'last_name' => 'Updated', 'username' => $user->username,
                'phone' => '+1 555 0110',
                'avatar' => UploadedFile::fake()->image('replacement.png', 96, 96),
            ])->assertOk();
        $user->refresh();
        $replacementPath = $user->profile_photo;
        $this->assertNotSame($firstPath, $replacementPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($replacementPath);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id, ['remove_avatar' => true])->assertOk()->assertJsonPath('user.avatar', null);
        $user->refresh();
        $this->assertNull($user->profile_photo);
        Storage::disk('public')->assertMissing($replacementPath);

        $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->post('/api/v1/admin/users/'.$admin->id, [
                '_method' => 'PATCH', 'name' => $admin->name, 'email' => $admin->email,
                'avatar' => UploadedFile::fake()->image('admin-avatar.png', 64, 64),
            ])->assertOk();
        $adminAvatarPath = $admin->fresh()->profile_photo;
        $this->assertStringStartsWith('user-avatars/', $adminAvatarPath);
        Storage::disk('public')->assertExists($adminAvatarPath);
    }

    public function test_avatar_upload_validation_rejects_unsupported_and_oversized_files(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $payload = [
            'name' => 'Invalid Avatar', 'email' => 'invalid-avatar-'.Str::uuid().'@gmail.com',
            'password' => 'Initial-Password-123', 'password_confirmation' => 'Initial-Password-123',
            'role' => 'user', 'status' => 'active',
        ];
        $headers = $this->headers() + ['Accept' => 'application/json'];

        $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->post('/api/v1/admin/users', $payload + ['avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml')])
            ->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->post('/api/v1/admin/users', $payload + ['avatar' => UploadedFile::fake()->image('oversized.png', 32, 32)->size(2500)])
            ->assertUnprocessable()->assertJsonValidationErrors('avatar');

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users', $payload + ['phone' => 'call-me-not-a-number'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');

        $target = User::factory()->create(['role' => 'user']);
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$target->id, ['phone' => 'not-a-phone'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_admin_can_update_supported_profile_fields_while_moderators_do_not_receive_private_profile_fields(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'name' => 'Before Name',
            'first_name' => 'Before',
            'last_name' => 'Name',
            'username' => 'before-'.Str::uuid(),
            'phone' => '5550100',
        ]);
        $originalPassword = $target->password;
        $username = 'after-'.Str::uuid();

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$target->id, [
                'name' => 'Updated Example',
                'first_name' => 'Updated',
                'last_name' => 'Example',
                'email' => 'updated-'.Str::uuid().'@gmail.com',
                'phone' => '5550199',
                'username' => $username,
            ])
            ->assertOk()
            ->assertJsonPath('user.first_name', 'Updated')
            ->assertJsonPath('user.last_name', 'Example')
            ->assertJsonPath('user.phone', '5550199')
            ->assertJsonPath('user.username', $username);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Example',
            'first_name' => 'Updated',
            'last_name' => 'Example',
            'phone' => '5550199',
            'username' => $username,
        ]);
        $this->assertSame($originalPassword, $target->fresh()->password, 'Profile edits must leave the existing password untouched.');

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('user.first_name', 'Updated')
            ->assertJsonPath('user.last_name', 'Example')
            ->assertJsonPath('user.phone', '5550199')
            ->assertJsonPath('user.username', $username)
            ->assertJsonMissingPath('user.password');

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$target->id, ['password' => 'Not-an-supported-change-123'])
            ->assertUnprocessable();

        $moderator = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => ['users.view']]);
        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.username');
    }

    public function test_search_filters_sorting_and_pagination_use_database_records(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Unique Search Person', 'email' => 'search-person@example.test', 'role' => 'user', 'status' => 'active']);
        User::factory()->create(['name' => 'Other Name', 'email' => 'other@example.test', 'role' => 'moderator', 'status' => 'suspended']);

        $response = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users?search=search-person&role=user&status=active&verification=verified&sort=name_asc&per_page=10');
        $response->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Unique Search Person');
        $this->assertArrayNotHasKey('password', $response->json('data.0'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.0'));

        User::factory()->count(12)->create(['role' => 'user']);
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users?role=user&per_page=10&page=2')
            ->assertOk()->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.per_page', 10);
    }

    public function test_user_pagination_uses_ten_rows_and_combines_search_filters_delete_and_restore(): void
    {
        $admin = $this->admin();
        $token = 'pagination-'.Str::uuid();
        $filteredIds = [];
        $today = now()->toDateString();

        for ($index = 1; $index <= 25; $index++) {
            $user = User::factory()->create([
                'name' => $token.' User '.$index,
                'email' => $token.'-'.$index.'@example.test',
                'role' => 'user',
                'status' => $index <= 11 ? 'active' : 'inactive',
                'created_at' => now(),
            ]);
            if ($index <= 11) $filteredIds[] = $user->id;
        }

        $searchOnly = '/api/v1/admin/users?search='.$token.'&per_page=10';
        $first = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson($searchOnly)->assertOk()
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 3);
        $second = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson($searchOnly.'&page=2')->assertOk()->assertJsonPath('meta.current_page', 2);
        $third = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson($searchOnly.'&page=3')->assertOk()->assertJsonPath('meta.last_page', 3);

        $this->assertCount(10, $first->json('data'));
        $this->assertCount(10, $second->json('data'));
        $this->assertCount(5, $third->json('data'));
        $this->assertCount(25, collect([$first, $second, $third])->flatMap(fn ($response) => collect($response->json('data'))->pluck('id'))->unique());
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson($searchOnly.'&per_page=11')->assertUnprocessable();

        $filterQuery = '/api/v1/admin/users?search='.$token.'&role=user&status=active&registered_from='.$today.'&registered_to='.$today.'&per_page=10';
        $filteredFirst = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson($filterQuery)->assertOk()->assertJsonPath('meta.total', 11)->assertJsonPath('meta.last_page', 2);
        $filteredSecond = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson($filterQuery.'&page=2')->assertOk()->assertJsonPath('meta.current_page', 2);
        $this->assertCount(10, $filteredFirst->json('data'));
        $this->assertCount(1, $filteredSecond->json('data'));
        $this->assertNotSame($filteredFirst->json('data.0.id'), $filteredSecond->json('data.0.id'));

        $smallToken = 'pagination-small-'.Str::uuid();
        User::factory()->count(10)->sequence(fn ($sequence) => [
            'name' => $smallToken.' User '.$sequence->index,
            'email' => $smallToken.'-'.$sequence->index.'@example.test',
            'role' => 'user',
        ])->create();
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users?search='.$smallToken.'&per_page=10')
            ->assertOk()->assertJsonPath('meta.total', 10)->assertJsonPath('meta.last_page', 1);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->deleteJson('/api/v1/admin/users/'.$filteredIds[10])->assertOk();
        $afterDeletePageTwo = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson($filterQuery.'&page=2')->assertOk()->assertJsonPath('meta.total', 10)->assertJsonPath('meta.last_page', 1);
        $this->assertCount(0, $afterDeletePageTwo->json('data'));

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users/'.$filteredIds[10].'/restore')->assertOk();
        $restoredPageTwo = $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson($filterQuery.'&page=2')->assertOk()->assertJsonPath('meta.total', 11)->assertJsonPath('meta.last_page', 2);
        $this->assertCount(1, $restoredPageTwo->json('data'));
    }

    public function test_admin_role_status_and_moderator_permission_changes_are_audited(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'user']);
        $base = '/api/v1/admin/users/'.$target->id;

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/role', ['role' => 'moderator', 'moderator_permissions' => ['users.view', 'activity.view']])
            ->assertOk()->assertJsonPath('user.role', 'moderator')->assertJsonPath('user.moderator_permissions.0', 'users.view');
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $target->id, 'actor_id' => $admin->id, 'event' => 'user.role_changed']);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->putJson($base.'/permissions', ['permissions' => ['users.view', 'activity.view', 'users.status.manage']])
            ->assertOk()->assertJsonPath('user.moderator_permissions.2', 'users.status.manage');

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/status', ['status' => 'suspended', 'reason' => 'Repeated policy violations'])
            ->assertOk()->assertJsonPath('user.status', 'suspended')->assertJsonPath('user.status_reason', 'Repeated policy violations');

        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $target->id, 'actor_id' => $admin->id, 'event' => 'account.suspended']);
    }

    public function test_moderator_may_only_use_explicitly_assigned_status_permission(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'user']);
        $moderator = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => ['users.view', 'users.status.manage']]);

        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$target->id.'/status', ['status' => 'suspended', 'reason' => 'Needs review'])
            ->assertOk()->assertJsonPath('user.status', 'suspended');

        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$admin->id.'/status', ['status' => 'banned', 'reason' => 'attempt'])
            ->assertForbidden();
    }

    public function test_moderator_cannot_restore_a_soft_deleted_user_directly(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => ['users.view', 'users.status.manage']]);
        $deletedUser = User::factory()->create(['role' => 'user']);
        $deletedUser->delete();

        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users/'.$deletedUser->id.'/restore')->assertForbidden();
        $this->assertSoftDeleted('users', ['id' => $deletedUser->id]);
    }

    public function test_suspended_and_banned_accounts_are_blocked_and_restorable(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user', 'password' => Hash::make('Member-Password-123')]);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id.'/status', ['status' => 'banned', 'reason' => 'Confirmed abuse'])
            ->assertOk();

        $this->actingAs($user->fresh(), 'web')->withHeaders($this->headers())
            ->getJson('/api/v1/user')->assertUnauthorized();

        $this->withHeaders($this->headers())->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Member-Password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id.'/status', ['status' => 'active'])
            ->assertOk()->assertJsonPath('user.status', 'active');
    }

    public function test_last_active_admin_cannot_be_demoted_suspended_banned_or_deleted(): void
    {
        $admin = $this->admin();
        $base = '/api/v1/admin/users/'.$admin->id;

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/role', ['role' => 'user'])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/status', ['status' => 'suspended', 'reason' => 'test reason'])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->deleteJson($base)->assertForbidden();
    }

    public function test_admin_can_edit_own_profile_but_cannot_change_own_role_status_or_delete_self(): void
    {
        $admin = $this->admin();
        $base = '/api/v1/admin/users/'.$admin->id;
        $newEmail = 'admin-updated-'.Str::uuid().'@gmail.com';

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base, ['name' => 'Updated Admin', 'email' => $newEmail, 'phone' => '5550101'])
            ->assertOk()->assertJsonPath('user.name', 'Updated Admin')->assertJsonPath('user.email', $newEmail);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'phone' => '5550101', 'role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/role', ['role' => 'user'])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->patchJson($base.'/status', ['status' => 'suspended', 'reason' => 'Self change attempt'])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->deleteJson($base)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'status' => 'active', 'deleted_at' => null]);
    }

    public function test_admin_can_change_only_their_own_password_after_current_password_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('Current-Password-123')]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('Current-Password-123')]);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users/'.$admin->id.'/password', [
                'current_password' => 'incorrect-password',
                'password' => 'New-Password-123',
                'password_confirmation' => 'New-Password-123',
            ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check('Current-Password-123', $admin->fresh()->password));

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users/'.$admin->id.'/password', [
                'current_password' => 'Current-Password-123',
                'password' => 'New-Password-123',
                'password_confirmation' => 'New-Password-123',
            ])->assertOk();
        $this->assertTrue(Hash::check('New-Password-123', $admin->fresh()->password));

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users/'.$otherAdmin->id.'/password', [
                'current_password' => 'Current-Password-123',
                'password' => 'New-Password-123',
                'password_confirmation' => 'New-Password-123',
            ])->assertForbidden();
        $this->assertTrue(Hash::check('Current-Password-123', $otherAdmin->fresh()->password));
    }

    public function test_admin_can_change_another_admin_status_and_delete_when_another_active_admin_remains(): void
    {
        $actor = $this->admin();
        $subject = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($actor, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$subject->id.'/status', ['status' => 'inactive'])
            ->assertOk()->assertJsonPath('user.status', 'inactive');
        $this->assertDatabaseHas('users', ['id' => $actor->id, 'role' => 'admin', 'status' => 'active']);

        $this->actingAs($actor, 'sanctum')->withHeaders($this->headers())
            ->deleteJson('/api/v1/admin/users/'.$subject->id)->assertOk();
        $this->assertSoftDeleted('users', ['id' => $subject->id]);
    }

    public function test_last_active_admin_is_protected_inside_management_service(): void
    {
        $actor = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $lastAdmin = $this->admin();
        User::query()->where('role', 'admin')->whereKeyNot($lastAdmin->id)->update(['status' => 'inactive']);
        $service = app(\App\Services\Api\V1\Admin\UserManagementService::class);

        try {
            $service->changeStatus($actor, $lastAdmin, 'inactive', null);
            $this->fail('The last active admin status should be protected.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertDatabaseHas('users', ['id' => $lastAdmin->id, 'role' => 'admin', 'status' => 'active']);
        }

        try {
            $service->softDelete($actor, $lastAdmin);
            $this->fail('The last active admin should not be deleted.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertDatabaseHas('users', ['id' => $lastAdmin->id, 'deleted_at' => null]);
        }
    }

    public function test_moderators_and_users_cannot_promote_themselves_to_admin(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator', 'moderator_permissions' => ['users.view']]);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($moderator, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$moderator->id.'/role', ['role' => 'admin'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->withHeaders($this->headers())
            ->patchJson('/api/v1/admin/users/'.$user->id.'/role', ['role' => 'admin'])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $moderator->id, 'role' => 'moderator']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'user']);
    }

    public function test_soft_deletion_is_audited_and_recoverable(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->deleteJson('/api/v1/admin/users/'.$user->id)->assertOk();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $user->id, 'event' => 'user.deleted']);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->postJson('/api/v1/admin/users/'.$user->id.'/restore')->assertOk()->assertJsonPath('user.status', 'active');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_activity_and_roles_endpoints_return_only_recorded_data_and_permissions(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user']);
        UserActivityLog::create([
            'user_id' => $user->id,
            'actor_id' => $admin->id,
            'event' => 'profile.updated',
            'description' => 'User profile updated.',
            'context' => ['nested' => ['session_id' => 'legacy-session-marker', 'status' => 'active']],
            'created_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users/activity?user_id='.$user->id)
            ->assertOk()
            ->assertJsonPath('data.0.event', 'profile.updated')
            ->assertJsonPath('data.0.context.nested', ['status' => 'active']);
        $this->actingAs($admin, 'sanctum')->withHeaders($this->headers())
            ->getJson('/api/v1/admin/users/roles-permissions')
            ->assertOk()->assertJsonPath('roles.moderator', 'Limited user visibility and only explicitly assigned moderation actions.');
    }
}
