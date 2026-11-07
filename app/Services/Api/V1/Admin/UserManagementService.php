<?php

namespace App\Services\Api\V1\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(private readonly UserActivityLogger $activityLogger)
    {
    }

    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $user = new User();
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = Hash::make($data['password']);
            $user->role = $data['role'];
            $user->status = $data['status'];
            $user->moderator_permissions = $data['role'] === 'moderator' ? ($data['moderator_permissions'] ?? []) : null;
            if ($data['status'] !== 'active') {
                $user->status_changed_by = $actor->id;
                $user->status_changed_at = now();
            }
            $user->save();

            $this->activityLogger->record('user.created', 'Administrator created a user account.', $user, $actor, ['role' => $user->role]);

            return $user->fresh('statusChangedBy:id,name');
        });
    }

    public function update(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data) {
            $user->fill($data)->save();
            $this->activityLogger->record('user.profile_updated', 'Administrator updated account information.', $user, $actor, ['fields' => array_keys($data)]);

            return $user->fresh('statusChangedBy:id,name');
        });
    }

    public function changeRole(User $actor, User $user, string $role, ?array $permissions = null): User
    {
        return DB::transaction(function () use ($actor, $user, $role, $permissions) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldRole = $user->role;
            $this->protectLastActiveAdmin($user, $oldRole !== 'admin' || $role === 'admin');

            $user->role = $role;
            $user->moderator_permissions = $role === 'moderator' ? ($permissions ?? ($oldRole === 'moderator' ? $user->moderator_permissions : [])) : null;
            $user->save();

            $this->activityLogger->record('user.role_changed', 'Administrator changed the account role.', $user, $actor, ['from' => $oldRole, 'to' => $role]);

            return $user->fresh('statusChangedBy:id,name');
        });
    }

    public function updateModeratorPermissions(User $actor, User $user, array $permissions): User
    {
        return DB::transaction(function () use ($actor, $user, $permissions) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->role === 'moderator', 422, 'Permissions can only be assigned to moderator accounts.');
            $before = $user->moderator_permissions ?? [];
            $user->moderator_permissions = array_values(array_unique($permissions));
            $user->save();
            $this->activityLogger->record('moderator.permissions_changed', 'Administrator updated moderator permissions.', $user, $actor, ['from' => $before, 'to' => $user->moderator_permissions]);

            return $user->fresh();
        });
    }

    public function changeStatus(User $actor, User $user, string $status, ?string $reason): User
    {
        return DB::transaction(function () use ($actor, $user, $status, $reason) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldStatus = $user->status;
            $this->protectLastActiveAdmin($user, $oldStatus !== 'active' || $status === 'active');

            $user->status = $status;
            $user->status_reason = in_array($status, ['suspended', 'banned'], true) ? $reason : null;
            $user->status_changed_by = $actor->id;
            $user->status_changed_at = now();
            $user->save();

            $event = match ($status) {
                'suspended' => 'account.suspended',
                'banned' => 'account.banned',
                'active' => in_array($oldStatus, ['suspended', 'banned', 'inactive'], true) ? 'account.restored' : 'account.status_changed',
                default => 'account.status_changed',
            };
            $this->activityLogger->record($event, 'Administrator changed account status.', $user, $actor, ['from' => $oldStatus, 'to' => $status, 'reason' => $user->status_reason]);

            return $user->fresh('statusChangedBy:id,name');
        });
    }

    public function softDelete(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->protectLastActiveAdmin($user, false);
            $this->activityLogger->record('user.deleted', 'Administrator moved the account to soft-deleted recovery.', $user, $actor, ['role' => $user->role]);
            $user->delete();
        });
    }

    public function restore(User $actor, User $user): User
    {
        return DB::transaction(function () use ($actor, $user) {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
            $user->restore();
            $previous = $user->status;
            $user->status = 'active';
            $user->status_reason = null;
            $user->status_changed_by = $actor->id;
            $user->status_changed_at = now();
            $user->save();
            $this->activityLogger->record('account.restored', 'Administrator restored the account.', $user, $actor, ['from' => $previous, 'to' => 'active']);

            return $user->fresh('statusChangedBy:id,name');
        });
    }

    private function protectLastActiveAdmin(User $user, bool $staysActiveAdmin): void
    {
        if ($user->role !== 'admin' || $user->status !== 'active' || $staysActiveAdmin) {
            return;
        }

        $otherActiveAdmins = User::query()
            ->where('role', 'admin')
            ->where('status', 'active')
            ->where('id', '<>', $user->id)
            ->lockForUpdate()
            ->count();

        if ($otherActiveAdmins === 0) {
            throw ValidationException::withMessages(['user' => ['The last active administrator cannot be removed, suspended, banned, or demoted.']]);
        }
    }
}
