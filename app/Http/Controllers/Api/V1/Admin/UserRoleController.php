<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ChangeUserRoleRequest;
use App\Http\Requests\Api\V1\Admin\ChangeUserStatusRequest;
use App\Http\Requests\Api\V1\Admin\UpdateModeratorPermissionsRequest;
use App\Http\Resources\Api\V1\AdminUserResource;
use App\Models\User;
use App\Services\Api\V1\Admin\UserManagementService;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    public function updateRole(ChangeUserRoleRequest $request, User $user, UserManagementService $users): JsonResponse
    {
        $this->authorize('changeRole', $user);
        $data = $request->validated();
        $updated = $users->changeRole($request->user(), $user, $data['role'], $data['moderator_permissions'] ?? null);

        return response()->json(['user' => AdminUserResource::make($updated)->resolve()]);
    }

    public function updateStatus(ChangeUserStatusRequest $request, User $user, UserManagementService $users): JsonResponse
    {
        $this->authorize('changeStatus', $user);
        $data = $request->validated();
        $updated = $users->changeStatus($request->user(), $user, $data['status'], $data['reason'] ?? null);

        return response()->json(['user' => AdminUserResource::make($updated)->resolve()]);
    }

    public function updatePermissions(UpdateModeratorPermissionsRequest $request, User $user, UserManagementService $users): JsonResponse
    {
        $this->authorize('changePermissions', $user);
        $updated = $users->updateModeratorPermissions($request->user(), $user, $request->validated('permissions'));

        return response()->json(['user' => AdminUserResource::make($updated)->resolve()]);
    }
}
