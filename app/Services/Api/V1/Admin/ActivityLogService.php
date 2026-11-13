<?php

namespace App\Services\Api\V1\Admin;

use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogService
{
    private const MODULE_SQL = "COALESCE(module, CASE WHEN event LIKE 'auth.%' THEN 'Authentication' WHEN event LIKE 'category.%' THEN 'Categories' WHEN event LIKE 'project.%' THEN 'Projects' WHEN event LIKE 'task.%' THEN 'Tasks' WHEN event LIKE 'report.%' THEN 'Reports' WHEN event LIKE 'settings.%' THEN 'Settings' ELSE 'Users' END)";

    public function paginate(User $viewer, array $filters): LengthAwarePaginator
    {
        $query = $this->filteredQuery($viewer, $filters);
        $sorts = [
            'created_at' => 'created_at',
            'event' => 'event',
            'module' => 'module',
            'status' => 'status',
            'actor' => 'actor_name',
        ];
        $sort = $sorts[$filters['sort'] ?? 'created_at'] ?? 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->reorder();
        if (($filters['sort'] ?? 'created_at') === 'module') {
            $query->orderByRaw(self::MODULE_SQL.' '.$direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query
            ->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 25), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function statistics(User $viewer, array $filters): array
    {
        $query = $this->filteredQuery($viewer, $filters);
        $today = CarbonImmutable::today();
        $todayCount = (clone $query)->whereBetween('created_at', [$today->startOfDay(), $today->endOfDay()])->count();
        $uniqueActors = (clone $query)->reorder()->selectRaw(
            "COUNT(DISTINCT CASE WHEN actor_id IS NOT NULL THEN CONCAT('id:', actor_id) WHEN actor_email IS NOT NULL THEN CONCAT('email:', actor_email) END) AS aggregate"
        )->value('aggregate');
        $securityEvents = (clone $query)->where(function (Builder $builder): void {
            $builder->where('event', 'like', 'auth.%')
                ->orWhereIn('event', [
                    'user.password_changed', 'user.role_changed', 'moderator.permissions_changed',
                    'account.suspended', 'account.banned', 'account.status_changed', 'account.restored',
                ]);
        })->count();

        return [
            'total_activities' => (clone $query)->count(),
            'today_activities' => $todayCount,
            'unique_actors' => (int) $uniqueActors,
            'security_events' => $securityEvents,
        ];
    }

    public function filterOptions(User $viewer, ?string $actorSearch = null): array
    {
        $scope = $this->visibleQuery($viewer);
        $modules = (clone $scope)->reorder()->selectRaw(self::MODULE_SQL.' AS resolved_module')
            ->distinct()->orderBy('resolved_module')->pluck('resolved_module')->values();
        $events = (clone $scope)->reorder()->whereNotNull('event')->select('event')
            ->distinct()->orderBy('event')->pluck('event')->values();
        $subjectTypes = (clone $scope)->reorder()
            ->where(fn (Builder $query) => $query->whereNotNull('subject_type')->orWhereNotNull('user_id'))
            ->selectRaw("COALESCE(subject_type, CASE WHEN user_id IS NOT NULL THEN 'User' END) AS resolved_subject_type")
            ->distinct()->orderBy('resolved_subject_type')->pluck('resolved_subject_type')->values();
        $statuses = (clone $scope)->reorder()->whereNotNull('status')->select('status')
            ->distinct()->orderBy('status')->pluck('status')->values();
        $actorIds = (clone $scope)->reorder()->whereNotNull('actor_id')->select('actor_id')->distinct();
        $actors = User::withTrashed()->whereIn('id', $actorIds)
            ->when(trim((string) $actorSearch) !== '', function (Builder $query) use ($actorSearch): void {
                $term = trim((string) $actorSearch);
                $query->where(fn (Builder $users) => $users
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%'));
            })
            ->orderBy('name')->orderBy('id')->limit(25)
            ->get(['id', 'name', 'email', 'role', 'deleted_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'deleted' => $user->trashed(),
            ]);

        return [
            'modules' => $modules->all(),
            'events' => $events->all(),
            'subject_types' => $subjectTypes->all(),
            'statuses' => $statuses->all(),
            'actors' => $actors->all(),
        ];
    }

    public function findVisible(User $viewer, int $id): UserActivityLog
    {
        return $this->visibleQuery($viewer)
            ->with(['actor:id,name,email,role,profile_photo,deleted_at', 'user:id,name,email,role,deleted_at'])
            ->findOrFail($id);
    }

    public function exportRecords(User $viewer, array $filters): iterable
    {
        $maxId = (int) (UserActivityLog::query()->max('id') ?? 0);
        $query = $this->filteredQuery($viewer, $filters)
            ->with(['actor:id,name,email,role,deleted_at', 'user:id,name,email,role,deleted_at'])
            ->where('user_activity_logs.id', '<=', $maxId)
            ->reorder();
        $lastId = 0;

        while (true) {
            $batch = (clone $query)->where('user_activity_logs.id', '>', $lastId)
                ->orderBy('user_activity_logs.id')->limit(500)->get();
            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $log) {
                yield $log;
                $lastId = $log->id;
            }
        }
    }

    public function normalizedFilters(array $filters): array
    {
        [$from, $to] = $this->dateRange($filters);
        $filters['from'] = $from->toDateString();
        $filters['to'] = $to->toDateString();

        return $filters;
    }

    private function filteredQuery(User $viewer, array $filters): Builder
    {
        $query = $this->visibleQuery($viewer);
        [$from, $to] = $this->dateRange($filters);
        $query->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', (int) $filters['actor_id']);
        }
        if (! empty($filters['module'])) {
            $query->whereRaw(self::MODULE_SQL.' = ?', [$filters['module']]);
        }
        if (! empty($filters['subject_type'])) {
            if ($filters['subject_type'] === 'User') {
                $query->where(fn (Builder $builder) => $builder
                    ->where('subject_type', 'User')
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('subject_type')->whereNotNull('user_id')));
            } else {
                $query->where('subject_type', $filters['subject_type']);
            }
        }
        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('description', 'like', '%'.$term.'%')
                    ->orWhere('event', 'like', '%'.$term.'%')
                    ->orWhere('module', 'like', '%'.$term.'%')
                    ->orWhere('subject_label', 'like', '%'.$term.'%')
                    ->orWhere('subject_id', 'like', '%'.$term.'%')
                    ->orWhere('actor_name', 'like', '%'.$term.'%')
                    ->orWhere('actor_email', 'like', '%'.$term.'%')
                    ->orWhereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
                    ->orWhereHas('user', fn (Builder $subject) => $subject->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
            });
        }

        return $query;
    }

    private function visibleQuery(User $viewer): Builder
    {
        $query = UserActivityLog::query()->with(['actor:id,name,email,role,profile_photo,deleted_at', 'user:id,name,email,role,deleted_at']);
        if ($viewer->role === 'moderator') {
            $query->whereIn('user_id', User::withTrashed()->where('role', '!=', 'admin')->select('id'))
                ->whereRaw(self::MODULE_SQL.' IN (?, ?)', ['Users', 'Authentication']);
        }

        return $query;
    }

    private function dateRange(array $filters): array
    {
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to']) : CarbonImmutable::today();
        $from = isset($filters['from']) ? CarbonImmutable::parse($filters['from']) : $to->subDays(29);

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
