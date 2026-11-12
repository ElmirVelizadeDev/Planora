<?php

namespace App\Services\Api\V1\Categories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UserCategoryCatalogService
{
    public function paginate(int $userId, array $filters): LengthAwarePaginator
    {
        $query = Category::query()->withCount([
            'projects as projects_count' => fn (Builder $builder) => $builder->where('owner_id', $userId),
            'tasks as tasks_count' => fn (Builder $builder) => $builder->where(fn (Builder $tasks) => $tasks
                ->where('owner_id', $userId)->orWhere('assigned_user_id', $userId)),
        ]);

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }

        $column = ($filters['sort'] ?? 'name') === 'created_at' ? 'created_at' : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 12))
            ->withQueryString();
    }

    public function findFor(int $categoryId, int $userId): Category
    {
        return Category::query()
            ->withCount([
                'projects as projects_count' => fn (Builder $builder) => $builder->where('owner_id', $userId),
                'tasks as tasks_count' => fn (Builder $builder) => $builder->where(fn (Builder $tasks) => $tasks
                    ->where('owner_id', $userId)->orWhere('assigned_user_id', $userId)),
            ])
            ->with([
                'projects' => fn ($builder) => $builder->select(['id', 'name', 'status', 'category_id', 'due_date'])
                    ->where('owner_id', $userId)->latest('id'),
                'tasks' => fn ($builder) => $builder->select(['id', 'title', 'status', 'priority', 'category_id', 'due_date'])
                    ->where(fn (Builder $tasks) => $tasks->where('owner_id', $userId)->orWhere('assigned_user_id', $userId))
                    ->latest('id'),
            ])
            ->findOrFail($categoryId);
    }
}
