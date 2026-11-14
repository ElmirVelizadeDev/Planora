<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->hasManagementPermission('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $user->role === 'admin' || $user->hasManagementPermission('tasks.view');
    }

    public function manageStatus(User $user, Task $task): bool
    {
        return $user->role === 'admin' || ($user->hasManagementPermission('tasks.view') && $user->hasManagementPermission('tasks.status.manage'));
    }
}
