<?php

namespace App\Services\Api\V1\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Builder;

class CategoryManagementService
{
    public function __construct(private readonly UserActivityLogger $activityLogger)
    {
    }

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

    public function create(array $data, User $actor): Category
    {
        try {
            return DB::transaction(function () use ($data, $actor): Category {
                $this->assertNameAvailable($data['name']);
                $category = Category::query()->create($data);
                $this->activityLogger->recordChange(
                    'category.created', 'Category created.', $category, $actor, [],
                    ['name' => $category->name, 'color' => $category->color],
                    ['description_changed' => filled($category->description)],
                );

                return $category;
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicateName($exception);
            throw $exception;
        }
    }

    public function update(Category $category, array $data, User $actor): Category
    {
        try {
            return DB::transaction(function () use ($category, $data, $actor): Category {
                $locked = Category::query()->lockForUpdate()->findOrFail($category->id);
                if (array_key_exists('name', $data)) {
                    $this->assertNameAvailable($data['name'], $locked->id);
                }

                $before = ['name' => $locked->name, 'color' => $locked->color];
                $locked->fill($data)->save();
                $changed = array_values(array_diff(array_keys($locked->getChanges()), ['updated_at']));
                if ($changed !== []) {
                    $after = ['name' => $locked->name, 'color' => $locked->color];
                    $safeFields = array_values(array_intersect($changed, ['name', 'color']));
                    if ($safeFields !== []) {
                        $this->activityLogger->recordChange(
                            'category.updated', 'Category updated.', $locked, $actor,
                            array_intersect_key($before, array_flip($safeFields)),
                            array_intersect_key($after, array_flip($safeFields)),
                            ['changed_fields' => $changed],
                        );
                    } else {
                        $this->activityLogger->record('category.updated', 'Category updated.', $locked, $actor, ['changed_fields' => $changed]);
                    }
                }

                return $locked->refresh();
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicateName($exception);
            throw $exception;
        }
    }

    public function archive(Category $category, User $actor): void
    {
        DB::transaction(function () use ($category, $actor): void {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->id);
            $this->activityLogger->record('category.archived', 'Category archived.', $locked, $actor);
            $locked->delete();
        });
    }

    public function restore(Category $category, User $actor): Category
    {
        try {
            return DB::transaction(function () use ($category, $actor): Category {
                $locked = Category::withTrashed()->lockForUpdate()->findOrFail($category->id);
                if (! $locked->trashed()) {
                    return $locked;
                }

                $this->assertNameAvailable($locked->name, $locked->id);
                $locked->restore();
                $this->activityLogger->record('category.restored', 'Category restored.', $locked, $actor);

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
