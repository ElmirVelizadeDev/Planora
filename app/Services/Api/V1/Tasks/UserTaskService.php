<?php

namespace App\Services\Api\V1\Tasks;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserTaskService
{
    public function queryFor(int $userId)
    {
        return Task::query()->where(fn ($query) => $query->where('owner_id', $userId)
            ->orWhere('assigned_user_id', $userId));
    }

    public function paginate(int $userId, array $filters): LengthAwarePaginator
    {
        $query = $this->queryFor($userId);

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['priority'])) $query->where('priority', $filters['priority']);

        $today = now()->toDateString();
        match ($filters['due'] ?? null) {
            'today' => $query->whereDate('due_date', $today),
            'upcoming' => $query->where('status', '!=', 'completed')->whereDate('due_date', '>', $today),
            'overdue' => $query->where('status', '!=', 'completed')->whereNotNull('due_date')->whereDate('due_date', '<', $today),
            default => null,
        };
        if (! empty($filters['due_after'])) $query->whereDate('due_date', '>=', $filters['due_after']);
        if (! empty($filters['due_before'])) $query->whereDate('due_date', '<=', $filters['due_before']);

        match ($filters['sort'] ?? (($filters['due'] ?? null) === 'upcoming' ? 'due_asc' : 'newest')) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'due_asc' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id'),
            'due_desc' => $query->orderByRaw('due_date IS NULL')->orderByDesc('due_date')->orderByDesc('id'),
            'priority_asc' => $query->orderByRaw("FIELD(priority, 'low', 'medium', 'high', 'urgent')")->orderByDesc('id'),
            'priority_desc' => $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")->orderByDesc('id'),
            default => $query->latest('created_at')->latest('id'),
        };

        return $query->paginate($filters['per_page'] ?? 10)->withQueryString();
    }

    public function statistics(int $userId): array
    {
        $scoped = $this->queryFor($userId);
        $counts = (clone $scoped)->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');

        return [
            'total' => (clone $scoped)->count(),
            'pending' => (int) ($counts['pending'] ?? 0),
            'in_progress' => (int) ($counts['in_progress'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'overdue' => (clone $scoped)->where('status', '!=', 'completed')->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString())->count(),
            'upcoming' => (clone $scoped)->where('status', '!=', 'completed')->whereNotNull('due_date')->whereDate('due_date', '>', now()->toDateString())->count(),
        ];
    }

    public function create(array $data, int $userId): Task
    {
        return Task::query()->create($data + [
            'owner_id' => $userId,
            'created_by' => $userId,
            'assigned_user_id' => null,
        ]);
    }

    public function update(Task $task, array $data): Task
    {
        $task->fill($data)->save();

        return $task->refresh();
    }

    public function updateStatus(Task $task, string $status): Task
    {
        $task->status = $status;
        $task->save();

        return $task->refresh();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }
}
