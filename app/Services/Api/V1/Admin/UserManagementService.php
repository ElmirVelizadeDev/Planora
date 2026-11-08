<?php

namespace App\Services\Api\V1\Admin;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UserManagementService
{
    public function __construct(private readonly UserActivityLogger $activityLogger)
    {
    }

    public function create(User $actor, array $data): User
    {
        $avatar = $data['avatar'] ?? null;
        $storedAvatar = null;

        try {
            return DB::transaction(function () use ($actor, $data, $avatar, &$storedAvatar) {
                $user = new User();
                $user->name = $data['name'];
                $user->first_name = $data['first_name'] ?? null;
                $user->last_name = $data['last_name'] ?? null;
                $user->email = $data['email'];
                $user->username = $data['username'] ?? null;
                $user->phone = $data['phone'] ?? null;
                $user->password = Hash::make($data['password']);
                $user->role = $data['role'];
                $user->status = $data['status'];
                $user->moderator_permissions = $data['role'] === 'moderator' ? ($data['moderator_permissions'] ?? []) : null;
                if ($avatar instanceof UploadedFile) {
                    $storedAvatar = $this->storeAvatar($avatar);
                    $user->profile_photo = $storedAvatar;
                }
                if ($data['status'] !== 'active') {
                    $user->status_changed_by = $actor->id;
                    $user->status_changed_at = now();
                }
                $user->save();

                $this->activityLogger->record('user.created', 'Administrator created a user account.', $user, $actor, [
                    'role' => $user->role,
                    'avatar_uploaded' => $user->profile_photo !== null,
                ]);

                return $user->fresh('statusChangedBy:id,name');
            });
        } catch (Throwable $exception) {
            if ($storedAvatar) Storage::disk('public')->delete($storedAvatar);
            throw $exception;
        }
    }

    public function update(User $actor, User $user, array $data): User
    {
        $avatar = $data['avatar'] ?? null;
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);
        unset($data['avatar'], $data['remove_avatar']);
        $oldAvatar = $user->profile_photo;
        $storedAvatar = null;

        try {
            $updated = DB::transaction(function () use ($actor, $user, $data, $avatar, $removeAvatar, &$storedAvatar) {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($avatar instanceof UploadedFile) {
                    $storedAvatar = $this->storeAvatar($avatar);
                    $data['profile_photo'] = $storedAvatar;
                } elseif ($removeAvatar) {
                    $data['profile_photo'] = null;
                }

                $user->fill($data)->save();
                $this->activityLogger->record('user.profile_updated', 'Administrator updated account information.', $user, $actor, [
                    'fields' => array_keys($data),
                    'avatar_removed' => $removeAvatar,
                    'avatar_replaced' => $avatar instanceof UploadedFile,
                ]);

                return $user->fresh('statusChangedBy:id,name');
            });
        } catch (Throwable $exception) {
            if ($storedAvatar) Storage::disk('public')->delete($storedAvatar);
            throw $exception;
        }

        if (($storedAvatar || $removeAvatar) && $oldAvatar !== $updated->profile_photo) {
            $this->deleteManagedAvatar($oldAvatar);
        }

        return $updated;
    }

    public function changePassword(User $actor, User $user, string $password): void
    {
        DB::transaction(function () use ($actor, $user, $password) {
            abort_unless($actor->is($user) && $actor->role === 'admin', 403, 'You can only change your own administrator password.');
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->password = Hash::make($password);
            $user->save();
            $this->activityLogger->record('user.password_changed', 'Administrator changed their account password.', $user, $actor);
        });
    }

    public function changeRole(User $actor, User $user, string $role, ?array $permissions = null): User
    {
        return DB::transaction(function () use ($actor, $user, $role, $permissions) {
            $this->lockAdministratorAccounts();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldRole = $user->role;
            abort_unless($actor->isNot($user), 403, 'You cannot change your own account role.');
            abort_unless($oldRole !== 'admin' || $role === 'admin', 403, 'Administrator accounts cannot be demoted.');
            $this->protectLastActiveAdmin($user, $oldRole === 'admin' && $role === 'admin');

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
            $this->lockAdministratorAccounts();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($actor->isNot($user), 403, 'You cannot change your own account status.');
            $oldStatus = $user->status;
            $this->protectLastActiveAdmin($user, $user->role === 'admin' && $status === 'active');

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
            $this->lockAdministratorAccounts();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($actor->isNot($user), 403, 'You cannot delete your own account.');
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
        if ($user->role !== 'admin' || $staysActiveAdmin) {
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

    /** Serialize critical administrator mutations so two concurrent requests cannot remove the last admins together. */
    private function lockAdministratorAccounts(): void
    {
        User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function storeAvatar(UploadedFile $avatar): string
    {
        $path = $avatar->storePublicly('user-avatars', 'public');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The avatar could not be stored.');
        }

        return $path;
    }

    private function deleteManagedAvatar(?string $path): void
    {
        if ($path && str_starts_with($path, 'user-avatars/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
