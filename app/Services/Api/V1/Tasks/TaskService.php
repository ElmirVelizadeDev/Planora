<?php

namespace App\Services\Api\V1\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\Api\V1\Admin\UserActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(private readonly UserActivityLogger $activityLogger)
    {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ! empty($filters['deleted']) ? Task::onlyTrashed() : Task::query();
        $query->with(['owner', 'assignedUser', 'creator', 'category']);

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['priority'])) $query->where('priority', $filters['priority']);
        if (! empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);

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
        $query = Task::query()->with(['owner', 'assignedUser', 'creator', 'category'])->where('project_id', $projectId);
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
        }
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['priority'])) $query->where('priority', $filters['priority']);
        if (! empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);
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

    public function create(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor): Task {
            $task = Task::create($data + ['created_by' => $actor->id]);
            $this->activityLogger->recordChange(
                'task.created', 'Task created.', $task, $actor, [],
                $this->auditValues($task, ['title', 'status', 'priority', 'due_date', 'owner_id', 'assigned_user_id', 'project_id', 'category_id']),
                ['description_changed' => filled($task->description)],
            );

            return $task->load(['owner', 'assignedUser', 'creator', 'category']);
        });
    }

    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            $fields = ['title', 'status', 'priority', 'due_date', 'owner_id', 'assigned_user_id', 'project_id', 'category_id'];
            $before = $this->auditValues($task, $fields);
            $task->fill($data);
            $changed = array_values(array_diff(array_keys($task->getDirty()), ['updated_at']));
            if ($changed !== []) {
                $task->save();
                $safeFields = array_values(array_intersect($changed, $fields));
                $event = $this->updateEvent($before, $this->auditValues($task, $safeFields), $safeFields);
                if ($safeFields !== []) {
                    $this->activityLogger->recordChange($event, $this->descriptionFor($event), $task, $actor,
                        array_intersect_key($before, array_flip($safeFields)), $this->auditValues($task, $safeFields),
                        ['changed_fields' => $changed]);
                } else {
                    $this->activityLogger->record('task.updated', 'Task updated.', $task, $actor, ['changed_fields' => $changed]);
                }
            }

            return $task->refresh()->load(['owner', 'assignedUser', 'creator', 'category']);
        });
    }

    public function delete(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor): void {
            $locked = Task::query()->lockForUpdate()->findOrFail($task->id);
            $this->activityLogger->record('task.deleted', 'Task moved to soft-deleted recovery.', $locked, $actor);
            $locked->delete();
        });
    }

    public function restore(int $taskId, User $actor): Task
    {
        return DB::transaction(function () use ($taskId, $actor): Task {
            $task = Task::withTrashed()->lockForUpdate()->findOrFail($taskId);
            abort_unless($task->trashed(), 409, 'This task is not in deleted recovery.');
            $task->restore();
            $this->activityLogger->record('task.restored', 'Task restored from deleted recovery.', $task, $actor);

            return $task->fresh()->load(['owner', 'assignedUser', 'creator', 'category']);
        });
    }

    private function updateEvent(array $before, array $after, array $fields): string
    {
        if (in_array('assigned_user_id', $fields, true) && ($before['assigned_user_id'] ?? null) !== ($after['assigned_user_id'] ?? null)) {
            return empty($before['assigned_user_id']) ? 'task.assigned' : 'task.reassigned';
        }
        if (in_array('status', $fields, true) && ($after['status'] ?? null) === 'completed') {
            return 'task.completed';
        }
        if (in_array('status', $fields, true) && ($before['status'] ?? null) !== ($after['status'] ?? null)) {
            return 'task.status_changed';
        }
        if (in_array('priority', $fields, true) && ($before['priority'] ?? null) !== ($after['priority'] ?? null)) {
            return 'task.priority_changed';
        }

        return 'task.updated';
    }

    private function descriptionFor(string $event): string
    {
        return match ($event) {
            'task.assigned' => 'Task assigned.',
            'task.reassigned' => 'Task reassigned.',
            'task.completed' => 'Task completed.',
            'task.status_changed' => 'Task status changed.',
            'task.priority_changed' => 'Task priority changed.',
            default => 'Task updated.',
        };
    }

    private function auditValues(Task $task, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $task->getAttributes()[$field] ?? $task->getRawOriginal($field);
        }

        return $values;
    }
}
