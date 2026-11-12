<?php

namespace App\Services\Api\V1\Admin;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Builder;

class CategoryManagementService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? 'active';
        $query = match ($status) {
            'archived' => Category::onlyTrashed(),
            'all' => Category::withTrashed(),
            default => Category::query(),
        };

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }

        $column = match ($filters['sort'] ?? 'name') {
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            default => 'name',
        };
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->withCount(['projects', 'tasks'])
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();
    }

    public function statistics(): array
    {
        return [
            'total' => Category::withTrashed()->count(),
            'active' => Category::query()->count(),
            'archived' => Category::onlyTrashed()->count(),
            'created_this_month' => Category::query()->where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    public function find(int $id, bool $withTrashed = false): Category
    {
        $query = $withTrashed ? Category::withTrashed() : Category::query();

        return $query->findOrFail($id);
    }

    public function findWithUsage(int $id): Category
    {
        return Category::withTrashed()
            ->withCount(['projects', 'tasks'])
            ->with([
                'projects' => fn ($query) => $query->select(['id', 'name', 'status', 'category_id', 'due_date'])->latest('id'),
                'tasks' => fn ($query) => $query->select(['id', 'title', 'status', 'priority', 'category_id', 'due_date'])->latest('id'),
            ])
            ->findOrFail($id);
    }

    public function create(array $data): Category
    {
        try {
            return DB::transaction(function () use ($data): Category {
                $this->assertNameAvailable($data['name']);

                return Category::query()->create($data);
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicateName($exception);
            throw $exception;
        }
    }

    public function update(Category $category, array $data): Category
    {
        try {
            return DB::transaction(function () use ($category, $data): Category {
                $locked = Category::query()->lockForUpdate()->findOrFail($category->id);
                if (array_key_exists('name', $data)) {
                    $this->assertNameAvailable($data['name'], $locked->id);
                }

                $locked->fill($data)->save();

                return $locked->refresh();
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicateName($exception);
            throw $exception;
        }
    }

    public function archive(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            Category::query()->lockForUpdate()->findOrFail($category->id)->delete();
        });
    }

    public function restore(Category $category): Category
    {
        try {
            return DB::transaction(function () use ($category): Category {
                $locked = Category::withTrashed()->lockForUpdate()->findOrFail($category->id);
                if (! $locked->trashed()) {
                    return $locked;
                }

                $this->assertNameAvailable($locked->name, $locked->id);
                $locked->restore();

                return $locked->refresh();
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicateName($exception);
            throw $exception;
        }
    }

    private function assertNameAvailable(string $name, ?int $exceptId = null): void
    {
        $query = Category::withTrashed()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))]);

        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => ['A category with this name already exists.']]);
        }
    }

    private function throwIfDuplicateName(QueryException $exception): void
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = mb_strtolower($exception->getMessage());
        if (in_array($sqlState, ['23000', '23505', '19'], true)
            && (str_contains($message, 'categories.name') || str_contains($message, 'categories_name_unique'))) {
            throw ValidationException::withMessages(['name' => ['A category with this name already exists.']]);
        }
    }
}
