<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexUserActivityRequest;
use App\Http\Resources\Api\V1\UserActivityResource;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;

class UserActivityController extends Controller
{
    public function index(IndexUserActivityRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $moderator = $request->user()->role === 'moderator';
        abort_unless($request->user()->can('viewActivity', User::class), 403);

        if ($moderator && isset($filters['user_id'])) {
            $target = User::withTrashed()->findOrFail($filters['user_id']);
            $this->authorize('view', $target);
        }

        $query = UserActivityLog::query()->with(['user:id,name,email,role', 'actor:id,name']);
        if ($moderator) {
            $query->whereHas('user', fn ($builder) => $builder->where('role', '!=', 'admin'));
        }
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn ($builder) => $builder
                ->where('description', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }

        $logs = $query->latest('created_at')->latest('id')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();

        return response()->json([
            'data' => UserActivityResource::collection($logs->getCollection())->resolve(),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage(), 'per_page' => $logs->perPage(), 'total' => $logs->total()],
        ]);
    }
}
