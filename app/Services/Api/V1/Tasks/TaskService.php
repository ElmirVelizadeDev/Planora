<?php

namespace App\Services\Api\V1\Tasks;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Task::query()->with(['owner', 'assignedUser', 'creator']);

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['priority'])) $query->where('priority', $filters['priority']);

        $assigneeId = $filters['assigned_user_id'] ?? $filters['user_id'] ?? null;
        if ($assigneeId) $query->where('assigned_user_id', $assigneeId);
        $endDate = $filters['end_date'] ?? $filters['due_before'] ?? null;
        $startDate = $filters['start_date'] ?? $filters['due_after'] ?? null;
        if (! empty($endDate)) $query->whereDate('due_date', '<=', $endDate);
        if (! empty($startDate)) $query->whereDate('due_date', '>=', $startDate);

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'due_asc' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id'),
            'due_desc' => $query->orderByRaw('due_date IS NULL')->orderByDesc('due_date')->orderByDesc('id'),
            'priority_asc' => $query->orderByRaw("FIELD(priority, 'low', 'medium', 'high', 'urgent')")->orderByDesc('id'),
            'priority_desc' => $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")->orderByDesc('id'),
            default => $query->latest('created_at')->latest('id'),
        };

        return $query->paginate($filters['per_page'] ?? 10)->withQueryString();
    }

    public function paginateForProject(int $projectId, array $filters): LengthAwarePaginator
    {
        $filters['project_id'] = $projectId;
        $query = Task::query()->with(['owner', 'assignedUser', 'creator'])->where('project_id', $projectId);
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
        }
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['priority'])) $query->where('priority', $filters['priority']);
        return $query->latest('created_at')->latest('id')->paginate($filters['per_page'] ?? 10)->withQueryString();
    }

    public function statistics(): array
    {
        $counts = Task::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');

        return [
            'total' => Task::query()->count(),
            'pending' => (int) ($counts['pending'] ?? 0),
            'in_progress' => (int) ($counts['in_progress'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'overdue' => Task::query()->where('status', '!=', 'completed')->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString())->count(),
        ];
    }

    public function create(array $data, int $adminId): Task
    {
        return DB::transaction(fn () => Task::create($data + ['created_by' => $adminId])->load(['owner', 'assignedUser', 'creator']));
    }

    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $task->fill($data)->save();

            return $task->refresh()->load(['owner', 'assignedUser', 'creator']);
        });
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }
}
