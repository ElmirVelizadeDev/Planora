<?php

namespace App\Policies;

use App\Models\User;

class UserManagementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === 'admin' || $actor->hasManagementPermission('users.view');
    }

    public function view(User $actor, User $subject): bool
    {
        return $actor->role === 'admin'
            || ($actor->hasManagementPermission('users.view') && $subject->role !== 'admin');
    }

    public function viewActivity(User $actor, User|string|null $subject = null): bool
    {
        return $actor->role === 'admin'
            || ($actor->hasManagementPermission('activity.view') && (! ($subject instanceof User) || $subject->role !== 'admin'));
    }

    public function update(User $actor, User $subject): bool
    {
        return $actor->role === 'admin';
    }

    public function changePassword(User $actor, User $subject): bool
    {
        return $actor->role === 'admin' && $actor->is($subject);
    }

    public function changeRole(User $actor, User $subject): bool
    {
        return $actor->role === 'admin'
            && $actor->isNot($subject)
            && $subject->role !== 'admin';
    }

    public function changePermissions(User $actor, User $subject): bool
    {
        return $actor->role === 'admin' && $subject->role === 'moderator';
    }

    public function changeStatus(User $actor, User $subject): bool
    {
        if ($actor->is($subject)) {
            return false;
        }

        return $actor->role === 'admin'
            || ($subject->role === 'user' && $actor->hasManagementPermission('users.view') && $actor->hasManagementPermission('users.status.manage'));
    }

    public function delete(User $actor, User $subject): bool
    {
        return $actor->role === 'admin' && $actor->isNot($subject);
    }

    public function restore(User $actor, User $subject): bool
    {
        return $actor->role === 'admin' && $subject->role !== 'admin';
    }
}
