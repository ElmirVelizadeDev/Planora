<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->hasManagementPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->role === 'admin' || $user->hasManagementPermission('projects.view');
    }

    public function manageStatus(User $user, Project $project): bool
    {
        return $user->role === 'admin' || ($user->hasManagementPermission('projects.view') && $user->hasManagementPermission('projects.status.manage'));
    }
}
