<?php

namespace App\Services\Api\V1\Admin;

use App\Models\User;
use App\Services\Api\V1\Notifications\NotificationService;
use App\Services\Api\V1\Shared\UserAvatarStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserManagementService
{
    public function __construct(
        private readonly UserActivityLogger $activityLogger,
        private readonly UserAvatarStorage $avatars,
        private readonly NotificationService $notifications,
    ) {
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
                    $storedAvatar = $this->avatars->store($avatar);
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
            if ($storedAvatar) $this->avatars->delete($storedAvatar);
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
                    $storedAvatar = $this->avatars->store($avatar);
                    $data['profile_photo'] = $storedAvatar;
                } elseif ($removeAvatar) {
                    $data['profile_photo'] = null;
                }

                $user->fill($data);
                $changed = array_values(array_diff(array_keys($user->getDirty()), ['updated_at']));
                if ($changed !== []) {
                    $user->save();
                    $event = in_array('profile_photo', $changed, true) ? 'user.avatar_changed' : 'user.profile_updated';
                    $description = $actor->is($user)
                        ? 'Account owner updated personal information.'
                        : 'Administrator updated account information.';
                    $this->activityLogger->record($event, $description, $user, $actor, [
                        'fields' => $changed,
                        'avatar_removed' => $removeAvatar,
                        'avatar_replaced' => $avatar instanceof UploadedFile,
                    ]);
                }

                return $user->fresh('statusChangedBy:id,name');
            });
        } catch (Throwable $exception) {
            if ($storedAvatar) $this->avatars->delete($storedAvatar);
            throw $exception;
        }

        if (($storedAvatar || $removeAvatar) && $oldAvatar !== $updated->profile_photo) {
            $this->avatars->deleteIfManaged($oldAvatar);
        }

        return $updated;
    }

    public function changePassword(User $actor, User $user, string $password): void
    {
        DB::transaction(function () use ($actor, $user, $password) {
            abort_unless($actor->is($user) && $actor->role === 'admin', 403, 'You can only change your own administrator password.');
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->password = Hash::make($password);
            $user->setRememberToken(\Illuminate\Support\Str::random(60));
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

            $newPermissions = $role === 'moderator' ? ($permissions ?? ($oldRole === 'moderator' ? $user->moderator_permissions : [])) : null;
            if ($oldRole === $role && $user->moderator_permissions === $newPermissions) {
                return $user;
            }
            $user->role = $role;
            $user->moderator_permissions = $newPermissions;
            $user->save();

            $activity = $this->activityLogger->recordChange('user.role_changed', 'Administrator changed the account role.', $user, $actor,
                ['role' => $oldRole], ['role' => $role], ['from' => $oldRole, 'to' => $role]);
            $this->notifications->accountRoleChanged($user, $actor, $oldRole, $role, $activity->event_id);

            return $user->fresh('statusChangedBy:id,name');
        });
    }

    public function updateModeratorPermissions(User $actor, User $user, array $permissions): User
    {
        return DB::transaction(function () use ($actor, $user, $permissions) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->role === 'moderator', 422, 'Permissions can only be assigned to moderator accounts.');
            $before = $user->moderator_permissions ?? [];
            $permissions = array_values(array_unique($permissions));
            if ($before === $permissions) return $user;
            $user->moderator_permissions = $permissions;
            $user->save();
            $activity = $this->activityLogger->recordChange('moderator.permissions_changed', 'Administrator updated moderator permissions.', $user, $actor,
                ['permissions' => $before], ['permissions' => $user->moderator_permissions], ['from' => $before, 'to' => $user->moderator_permissions]);
            $this->notifications->moderatorPermissionsChanged($user, $actor, $activity->event_id);

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
            $oldReason = $user->status_reason;
            if ($oldStatus === $status && $oldReason === (in_array($status, ['suspended', 'banned'], true) ? $reason : null)) {
                return $user;
            }
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
            $activity = $this->activityLogger->recordChange($event, 'Administrator changed account status.', $user, $actor,
                ['status' => $oldStatus], ['status' => $status], ['from' => $oldStatus, 'to' => $status]);
            $this->notifications->accountStatusChanged($user, $actor, $oldStatus, $status, $activity->event_id);

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
            $activity = $this->activityLogger->record('user.deleted', 'Administrator moved the account to soft-deleted recovery.', $user, $actor, ['role' => $user->role]);
            $user->delete();
            $this->notifications->accountDeleted($user, $actor, $activity->event_id);
        });
    }

    public function restore(User $actor, User $user): User
    {
        return DB::transaction(function () use ($actor, $user) {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->trashed(), 409, 'This account is not deleted.');
            $user->restore();
            $previous = $user->status;
            $user->status = 'active';
            $user->status_reason = null;
            $user->status_changed_by = $actor->id;
            $user->status_changed_at = now();
            $user->save();
            $activity = $this->activityLogger->record('account.restored', 'Administrator restored the account.', $user, $actor,
                ['from' => $previous, 'to' => 'active'], 'Users', 'success', ['status' => $previous], ['status' => 'active']);
            $this->notifications->accountRestored($user, $actor, $activity->event_id);

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

}
