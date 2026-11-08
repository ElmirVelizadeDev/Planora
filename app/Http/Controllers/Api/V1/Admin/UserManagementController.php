<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexUsersRequest;
use App\Http\Requests\Api\V1\Admin\ChangeManagedUserPasswordRequest;
use App\Http\Requests\Api\V1\Admin\StoreManagedUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateManagedUserRequest;
use App\Http\Resources\Api\V1\AdminUserResource;
use App\Models\User;
use App\Services\Api\V1\Admin\UserManagementQueryService;
use App\Services\Api\V1\Admin\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(IndexUsersRequest $request, UserManagementQueryService $queries): JsonResponse
    {
        $filters = $request->validated();
        $moderatorView = $request->user()->role === 'moderator';
        abort_if($moderatorView && ! empty($filters['deleted']), 403);
        $users = $queries->users($filters, $moderatorView);

        return response()->json([
            'data' => AdminUserResource::collection($users->getCollection())->resolve(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'active_admins' => $request->user()->role === 'admin'
                    ? User::query()->where('role', 'admin')->where('status', 'active')->count()
                    : null,
                'statistics' => $queries->statistics($filters['role'] ?? null, $moderatorView),
            ],
        ]);
    }

    public function store(StoreManagedUserRequest $request, UserManagementService $users): JsonResponse
    {
        $user = $users->create($request->user(), $request->validated());

        return response()->json(['user' => AdminUserResource::make($user)->resolve()], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);
        $user->load('statusChangedBy:id,name');
        $activity = $request->user()->can('viewActivity', $user)
            ? $user->activity()->with(['user:id,name,email', 'actor:id,name'])->limit(25)->get()
            : collect();

        return response()->json([
            'user' => AdminUserResource::make($user)->resolve(),
            'activity' => \App\Http\Resources\Api\V1\UserActivityResource::collection($activity)->resolve(),
        ]);
    }

    public function update(UpdateManagedUserRequest $request, User $user, UserManagementService $users): JsonResponse
    {
        return response()->json(['user' => AdminUserResource::make($users->update($request->user(), $user, $request->validated()))->resolve()]);
    }

    public function changePassword(ChangeManagedUserPasswordRequest $request, User $user, UserManagementService $users): JsonResponse
    {
        $users->changePassword($request->user(), $user, $request->validated('password'));

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function destroy(Request $request, User $user, UserManagementService $users): JsonResponse
    {
        $this->authorize('delete', $user);
        $users->softDelete($request->user(), $user);

        return response()->json(['message' => 'Account moved to recoverable deleted state.']);
    }

    public function restore(Request $request, int $userId, UserManagementService $users): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($userId);
        $this->authorize('restore', $user);

        return response()->json(['user' => AdminUserResource::make($users->restore($request->user(), $user))->resolve()]);
    }
}
