<?php

namespace App\Http\Controllers\Api\V1\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\Users\AdminUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class UserRolePermissionsController extends Controller
{
    public function index(): JsonResponse
    {
        $catalog = config('user_management.moderator_permissions', []);
        $permissions = collect($catalog)->mapWithKeys(fn (array $definition, string $key) => [$key => $definition['description']])->all();

        return response()->json([
            'roles' => config('user_management.roles'),
            // Keep the legacy catalog response stable for existing Users workflows.
            'permissions' => $permissions,
            'permission_catalog' => $catalog,
            'matrix' => [
                'user' => [],
                'moderator' => 'assigned_per_account',
                'admin' => array_keys($catalog),
            ],
        ]);
    }

    public function moderators(Request $request): JsonResponse
    {
        $filters = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $query = User::query()->where('role', 'moderator');
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"));
        }
        $paginator = $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 10)->withQueryString();
        $catalog = config('user_management.moderator_permissions', []);

        return response()->json([
            'data' => AdminUserResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
                'role_counts' => [
                    'admin' => User::query()->where('role', 'admin')->count(),
                    'moderator' => User::query()->where('role', 'moderator')->count(),
                    'user' => User::query()->where('role', 'user')->count(),
                ],
                'permission_catalog' => $catalog,
                'recent_access_changes' => \App\Models\UserActivityLog::query()
                    ->where('event', 'moderator.permissions_changed')->latest('created_at')->limit(5)
                    ->get(['id', 'event', 'description', 'subject_label', 'actor_name', 'created_at'])
                    ->map(fn ($log) => [
                        'id' => $log->id, 'event' => $log->event, 'description' => $log->description,
                        'moderator' => $log->subject_label, 'actor' => $log->actor_name,
                        'created_at' => $log->created_at?->toIso8601String(),
                    ])->all(),
            ],
        ]);
    }

    public function showModerator(User $user): JsonResponse
    {
        abort_unless($user->role === 'moderator', 404);

        return response()->json(['data' => AdminUserResource::make($user)->resolve(), 'permission_catalog' => config('user_management.moderator_permissions', [])]);
    }
}
