<?php

namespace App\Services\Api\V1\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserManagementQueryService
{
    public function statistics(?string $role = null, bool $moderatorView = false): array
    {
        $query = User::query();
        if ($moderatorView) {
            $query->where('role', '!=', 'admin');
        }
        if ($role) {
            $query->where('role', $role);
        }

        $now = now();
        $newUsersStart = $now->copy()->subDays(30)->startOfDay();

        $row = (clone $query)->selectRaw(
            "COUNT(*) AS total_users,
            COALESCE(SUM(status = 'active'), 0) AS active_users,
            COALESCE(SUM(status = 'inactive'), 0) AS inactive_users,
            COALESCE(SUM(status = 'suspended'), 0) AS suspended_users,
            COALESCE(SUM(status = 'banned'), 0) AS banned_users,
            COALESCE(SUM(role = 'user' AND created_at >= ? AND created_at <= ?), 0) AS new_users_last_30_days",
            [$newUsersStart, $now]
        )->toBase()->first();

        return collect((array) $row)->map(fn ($value) => (int) $value)->all();
    }

    public function users(array $filters, bool $moderatorView = false)
    {
        $query = User::query()->with('statusChangedBy:id,name');
        if (! empty($filters['deleted'])) {
            $query = User::withTrashed()->onlyTrashed()->with('statusChangedBy:id,name');
        }

        if ($moderatorView) {
            $query->where('role', '!=', 'admin');
        }
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (($filters['verification'] ?? null) === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif (($filters['verification'] ?? null) === 'unverified') {
            $query->whereNull('email_verified_at');
        }
        if (! empty($filters['registered_from'])) {
            $query->whereDate('created_at', '>=', $filters['registered_from']);
        }
        if (! empty($filters['registered_to'])) {
            $query->whereDate('created_at', '<=', $filters['registered_to']);
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderBy('id'),
            default => $query->latest('created_at')->orderByDesc('id'),
        };

        return $query->paginate((int) ($filters['per_page'] ?? 10))->withQueryString();
    }
}
