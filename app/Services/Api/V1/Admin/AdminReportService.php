<?php

namespace App\Services\Api\V1\Admin;

use App\Models\Category;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AdminReportService
{
    public function build(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $previousFrom = $from->subDays($days);
        $previousTo = $from->subDay()->endOfDay();

        $users = $this->userQuery($filters);
        $projects = $this->projectQuery($filters);
        $tasks = $this->taskQuery($filters);

        $userStatusCounts = $this->groupedCounts($users, 'status');
        $userRoleCounts = $this->groupedCounts($users, 'role');
        $newUsers = $this->createdIn($users, $from, $to)->count();
        $previousNewUsers = $this->createdIn($users, $previousFrom, $previousTo)->count();

        $projectStatuses = $this->groupedCounts($projects, 'status');
        $newProjects = $this->createdIn($projects, $from, $to)->count();
        $previousNewProjects = $this->createdIn($projects, $previousFrom, $previousTo)->count();

        $taskStatuses = $this->groupedCounts($tasks, 'status');
        $taskPriorities = $this->groupedCounts($tasks, 'priority');
        $periodTasks = $this->createdIn($tasks, $from, $to);
        $newTasks = $this->createdIn($tasks, $from, $to)->count();
        $previousNewTasks = $this->createdIn($tasks, $previousFrom, $previousTo)->count();
        $overdue = (clone $tasks)->where('status', '!=', 'completed')
            ->whereNotNull('due_date')->whereDate('due_date', '<', today()->toDateString());

        $categoryQuery = $this->categoryUsageQuery($filters, $from, $to);
        $projectCategoryRows = (clone $categoryQuery)->reorder()->orderByDesc('projects_count')->orderBy('categories.name')->limit(8)->get();
        $taskCategoryRows = (clone $categoryQuery)->reorder()->orderByDesc('tasks_count')->orderBy('categories.name')->limit(8)->get();
        $leastProjectCategoryRows = (clone $categoryQuery)->reorder()->orderBy('projects_count')->orderBy('categories.name')->limit(5)->get();
        $leastTaskCategoryRows = (clone $categoryQuery)->reorder()->orderBy('tasks_count')->orderBy('categories.name')->limit(5)->get();
        $tableFilters = array_merge($filters, ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'page' => 1, 'per_page' => 10]);
        $categoryActive = Category::query()->count();
        $categoryArchived = Category::onlyTrashed()->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
            ],
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'project_id' => $filters['project_id'] ?? null,
                'task_status' => $filters['task_status'] ?? null,
                'category_id' => $filters['category_id'] ?? null,
            ],
            'overview' => [
                'users' => [
                    'total' => array_sum($userStatusCounts),
                    'active' => $userStatusCounts['active'] ?? 0,
                    'inactive' => $userStatusCounts['inactive'] ?? 0,
                    'suspended' => $userStatusCounts['suspended'] ?? 0,
                    'banned' => $userStatusCounts['banned'] ?? 0,
                    'new_in_period' => $newUsers,
                    'previous_period' => $previousNewUsers,
                    'growth_percent' => $this->growthPercent($newUsers, $previousNewUsers),
                ],
                'projects' => [
                    'total' => array_sum($projectStatuses),
                    'planned' => $projectStatuses['planned'] ?? 0,
                    'active' => $projectStatuses['active'] ?? 0,
                    'on_hold' => $projectStatuses['on_hold'] ?? 0,
                    'completed' => $projectStatuses['completed'] ?? 0,
                    'new_in_period' => $newProjects,
                    'previous_period' => $previousNewProjects,
                    'growth_percent' => $this->growthPercent($newProjects, $previousNewProjects),
                    'completion_rate' => $this->rate($projectStatuses['completed'] ?? 0, array_sum($projectStatuses)),
                ],
                'tasks' => [
                    'total' => array_sum($taskStatuses),
                    'pending' => $taskStatuses['pending'] ?? 0,
                    'in_progress' => $taskStatuses['in_progress'] ?? 0,
                    'completed' => $taskStatuses['completed'] ?? 0,
                    'overdue' => $overdue->count(),
                    'created_in_period' => $newTasks,
                    'previous_period' => $previousNewTasks,
                    'growth_percent' => $this->growthPercent($newTasks, $previousNewTasks),
                    'completion_rate' => $this->rate($taskStatuses['completed'] ?? 0, array_sum($taskStatuses)),
                ],
                'categories' => ['active' => $categoryActive, 'archived' => $categoryArchived],
            ],
            'user_analytics' => [
                'registration_trend' => $this->dailySeries($users, $from, $to),
                'status_distribution' => $this->distribution($userStatusCounts, ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'banned' => 'Banned']),
                'role_distribution' => $this->distribution($userRoleCounts, ['user' => 'User', 'moderator' => 'Moderator', 'admin' => 'Admin']),
                'recent_registrations' => $this->tableData('registrations', $tableFilters),
                'growth_percent' => $this->growthPercent($newUsers, $previousNewUsers),
            ],
            'project_analytics' => [
                'creation_trend' => $this->dailySeries($projects, $from, $to),
                'status_distribution' => $this->distribution($projectStatuses, ['planned' => 'Planned', 'active' => 'Active', 'on_hold' => 'On hold', 'completed' => 'Completed']),
                'category_usage' => $this->categoryDistribution($projectCategoryRows, 'projects_count'),
                'recent_projects' => $this->tableData('projects', $tableFilters),
            ],
            'task_analytics' => [
                'creation_trend' => $this->dailySeries($tasks, $from, $to),
                'status_distribution' => $this->distribution($taskStatuses, ['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed']),
                'priority_distribution' => $this->distribution($taskPriorities, ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent']),
                'category_usage' => $this->categoryDistribution($taskCategoryRows, 'tasks_count'),
                'assignee_distribution' => $this->assigneeDistribution($periodTasks),
                'assignees' => $this->tableData('assignees', $tableFilters),
                'recent_tasks' => $this->tableData('tasks', $tableFilters),
                'overdue_tasks' => $this->tableData('overdue', $tableFilters),
                'upcoming_deadlines' => $this->tableData('upcoming', $tableFilters),
                'completion_history_available' => false,
            ],
            'category_analytics' => [
                'active_count' => $categoryActive,
                'archived_count' => $categoryArchived,
                'project_usage' => $this->categoryDistribution($projectCategoryRows, 'projects_count'),
                'task_usage' => $this->categoryDistribution($taskCategoryRows, 'tasks_count'),
                'least_project_usage' => $this->categoryDistribution($leastProjectCategoryRows, 'projects_count', 'asc'),
                'least_task_usage' => $this->categoryDistribution($leastTaskCategoryRows, 'tasks_count', 'asc'),
                'unused' => $this->tableData('unused_categories', $tableFilters),
                'categories' => $this->tableData('categories', $tableFilters),
            ],
        ];
    }

    public function filterOptions(string $type, ?string $search): array
    {
        $term = trim((string) $search);

        return match ($type) {
            'users' => User::query()->select(['id', 'name', 'role', 'status'])
                ->when($term !== '', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                ->orderBy('name')->orderBy('id')->limit(25)->get()->toArray(),
            'projects' => Project::query()->select(['id', 'name', 'status'])
                ->when($term !== '', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                ->orderBy('name')->orderBy('id')->limit(25)->get()->toArray(),
            default => Category::withTrashed()->select(['id', 'name', 'deleted_at'])
                ->when($term !== '', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                ->orderBy('name')->orderBy('id')->limit(25)->get()
                ->map(fn (Category $category) => ['id' => $category->id, 'name' => $category->name, 'status' => $category->trashed() ? 'archived' : 'active'])
                ->all(),
        };
    }

    public function tableData(string $table, array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = (string) ($filters['sort'] ?? '');
        $direction = ($filters['direction'] ?? null) === 'asc' ? 'asc' : 'desc';

        $query = match ($table) {
            'registrations' => $this->createdIn($this->userQuery($filters), $from, $to),
            'projects' => $this->createdIn($this->projectQuery($filters), $from, $to)->with(['category:id,name,color,deleted_at']),
            'tasks' => $this->createdIn($this->taskQuery($filters), $from, $to)->with(['project:id,name', 'category:id,name,color,deleted_at']),
            'overdue' => $this->taskQuery($filters)->where('status', '!=', 'completed')->whereNotNull('due_date')
                ->whereDate('due_date', '<', today()->toDateString())->with(['project:id,name', 'category:id,name,color,deleted_at']),
            'upcoming' => $this->taskQuery($filters)->where('status', '!=', 'completed')->whereNotNull('due_date')
                ->whereDate('due_date', '>=', today()->toDateString())->whereDate('due_date', '<=', today()->addDays(13)->toDateString())
                ->with(['project:id,name', 'category:id,name,color,deleted_at']),
            'categories', 'unused_categories' => $this->categoryTableQuery($table, $filters, $from, $to),
            'assignees' => $this->assigneeTableQuery($filters, $from, $to),
            default => throw new \InvalidArgumentException('Unknown report table.'),
        };

        match ($table) {
            'registrations' => $query->select(['users.id', 'users.name', 'users.role', 'users.status', 'users.created_at']),
            'projects' => $query->select(['projects.id', 'projects.name', 'projects.status', 'projects.category_id', 'projects.created_at']),
            'tasks', 'overdue', 'upcoming' => $query->select(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.priority', 'tasks.due_date', 'tasks.project_id', 'tasks.category_id', 'tasks.created_at']),
            default => null,
        };

        if ($search !== '') {
            $column = match ($table) {
                'registrations', 'projects', 'categories', 'unused_categories' => 'name',
                'assignees' => 'users.name',
                default => 'title',
            };
            $query->where($column, 'like', '%'.$search.'%');
        }

        $allowedSorts = match ($table) {
            'registrations' => ['name' => 'name', 'role' => 'role', 'status' => 'status', 'created_at' => 'created_at'],
            'projects' => ['name' => 'name', 'status' => 'status', 'created_at' => 'created_at'],
            'tasks', 'overdue', 'upcoming' => ['title' => 'title', 'status' => 'status', 'priority' => 'priority', 'due_date' => 'due_date', 'created_at' => 'created_at'],
            'categories', 'unused_categories' => ['name' => 'name', 'status' => 'deleted_at', 'projects' => 'projects_count', 'tasks' => 'tasks_count'],
            'assignees' => ['name' => 'users.name', 'role' => 'users.role', 'status' => 'users.status', 'tasks' => 'tasks_count'],
        };
        $defaultSort = match ($table) {
            'registrations', 'projects', 'tasks' => 'created_at',
            'overdue', 'upcoming' => 'due_date',
            'categories', 'unused_categories' => 'name',
            'assignees' => 'tasks',
        };
        $sortColumn = $allowedSorts[$sort] ?? $allowedSorts[$defaultSort];
        if (! isset($filters['direction'])) {
            $direction = in_array($table, ['overdue', 'upcoming', 'categories', 'unused_categories'], true) ? 'asc' : 'desc';
        }
        $query->orderBy($sortColumn, $direction);
        $tieBreaker = match ($table) {
            'registrations' => 'users.id',
            'projects' => 'projects.id',
            'categories', 'unused_categories' => 'categories.id',
            'assignees' => 'users.id',
            default => 'tasks.id',
        };
        $query->orderBy($tieBreaker);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $rows = $paginator->items();
        if (in_array($table, ['categories', 'unused_categories'], true)) {
            $rows = array_map(fn (Category $category) => $this->categorySummary($category), $rows);
        }

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function exportRows(array $report, array $filters): iterable
    {
        $period = $report['period']['from'].' to '.$report['period']['to'];
        foreach ($report['overview'] as $section => $metrics) {
            foreach ($metrics as $metric => $value) {
                yield [ucfirst($section), '', '', '', '', '', '', '', '', str_replace('_', ' ', $metric), $value, $period];
            }
        }

        foreach (['Users' => $report['user_analytics']['registration_trend'], 'Projects' => $report['project_analytics']['creation_trend'], 'Tasks' => $report['task_analytics']['creation_trend']] as $section => $series) {
            foreach ($series as $point) {
                yield [$section.' trend', '', '', '', '', '', '', '', '', $point['date'], $point['value'], $period];
            }
        }

        $sections = [
            'registrations' => 'Recent registrations', 'projects' => 'Recent projects', 'tasks' => 'Recent tasks',
            'overdue' => 'Overdue tasks', 'upcoming' => 'Upcoming deadlines', 'assignees' => 'Task assignments',
            'categories' => 'Category usage', 'unused_categories' => 'Unused categories',
        ];
        foreach ($sections as $table => $section) {
            $page = 1;
            do {
                $pageData = $this->tableData($table, array_merge($filters, ['page' => $page, 'per_page' => 100]));
                foreach ($pageData['data'] as $record) {
                    yield $this->exportRecord($section, $record, $period);
                }
                $page++;
            } while ($page <= $pageData['meta']['last_page']);
        }
    }

    private function exportRecord(string $section, mixed $record, string $period): array
    {
        $value = is_object($record) && method_exists($record, 'toArray') ? $record->toArray() : (array) $record;
        $category = $value['category']['name'] ?? '';
        if ($category !== '' && ! empty($value['category']['deleted_at'])) {
            $category .= ' (archived)';
        }
        $project = $value['project']['name'] ?? '';
        $name = $value['title'] ?? $value['name'] ?? '';
        $status = $value['status'] ?? '';
        if (($value['account_status'] ?? null) === 'Archived account') {
            $status = 'Archived account'.($status !== '' ? ' — '.$status : '');
        }
        $metric = isset($value['tasks_count']) ? 'Tasks assigned' : (isset($value['projects']) && isset($value['tasks']) ? 'Projects / tasks' : '');
        $metricValue = isset($value['tasks_count']) ? $value['tasks_count'] : (isset($value['projects']) && isset($value['tasks']) ? $value['projects'].' / '.$value['tasks'] : '');

        return [
            $section, $value['id'] ?? '', $name, $value['role'] ?? '', $status, $value['priority'] ?? '',
            $category, $project, $value['due_date'] ?? '', $value['created_at'] ?? '',
            $metric, $metricValue, $period,
        ];
    }

    private function categoryTableQuery(string $table, array $filters, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = $this->categoryUsageQuery($filters, $from, $to);
        if ($table === 'unused_categories') {
            $projectUsage = fn (Builder $builder) => $this->applyProjectUsageFilters($builder, $filters, $from, $to);
            $taskUsage = fn (Builder $builder) => $this->applyTaskUsageFilters($builder, $filters, $from, $to);
            $query->whereDoesntHave('projects', $projectUsage)->whereDoesntHave('tasks', $taskUsage);
        }

        return $query;
    }

    private function assigneeTableQuery(array $filters, CarbonImmutable $from, CarbonImmutable $to): QueryBuilder
    {
        $assignmentCounts = $this->createdIn($this->taskQuery($filters), $from, $to)
            ->whereNotNull('assigned_user_id')->select('assigned_user_id')->selectRaw('COUNT(*) as tasks_count')->groupBy('assigned_user_id');

        return DB::query()->fromSub($assignmentCounts, 'assignment_counts')
            ->join('users', 'users.id', '=', 'assignment_counts.assigned_user_id')
            ->select(['users.id', 'users.name', 'users.role', 'users.status', 'assignment_counts.tasks_count'])
            ->selectRaw("CASE WHEN users.deleted_at IS NULL THEN 'Current' ELSE 'Archived account' END AS account_status");
    }

    private function period(array $filters): array
    {
        $today = CarbonImmutable::today();
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to']) : $today;
        $from = isset($filters['from']) ? CarbonImmutable::parse($filters['from']) : $to->subDays(29);

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function userQuery(array $filters): Builder
    {
        return User::query()->when($filters['user_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id));
    }

    private function projectQuery(array $filters): Builder
    {
        return Project::query()
            ->when($filters['user_id'] ?? null, fn (Builder $query, $id) => $query->where('owner_id', $id))
            ->when($filters['project_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('category_id', $id));
    }

    private function taskQuery(array $filters): Builder
    {
        return Task::query()
            ->when($filters['user_id'] ?? null, fn (Builder $query, $id) => $query->where(fn (Builder $tasks) => $tasks->where('owner_id', $id)->orWhere('assigned_user_id', $id)))
            ->when($filters['project_id'] ?? null, fn (Builder $query, $id) => $query->where('project_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('category_id', $id))
            ->when($filters['task_status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status));
    }

    private function createdIn(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return (clone $query)->whereBetween('created_at', [$from, $to]);
    }

    private function groupedCounts(Builder $query, string $column): array
    {
        return (clone $query)->selectRaw("{$column}, COUNT(*) as total")->groupBy($column)
            ->pluck('total', $column)->map(fn ($value) => (int) $value)->all();
    }

    private function dailySeries(Builder $query, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = $this->createdIn($query, $from, $to)
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as total')
            ->groupBy('report_date')->orderBy('report_date')->get()->mapWithKeys(fn ($row) => [$row->report_date => (int) $row->total]);
        $series = [];

        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $series[] = ['date' => $date, 'value' => $counts->get($date, 0)];
        }

        return $series;
    }

    private function distribution(array $counts, array $labels): array
    {
        $items = [];
        foreach ($labels as $value => $label) {
            $items[] = ['key' => $value, 'label' => $label, 'value' => (int) ($counts[$value] ?? 0)];
        }

        return $items;
    }

    private function categoryUsageQuery(array $filters, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $projectUsage = fn (Builder $query) => $this->applyProjectUsageFilters($query, $filters, $from, $to);
        $taskUsage = fn (Builder $query) => $this->applyTaskUsageFilters($query, $filters, $from, $to);

        return Category::withTrashed()->select(['categories.id', 'categories.name', 'categories.color', 'categories.deleted_at'])->withCount([
            'projects as projects_count' => $projectUsage,
            'tasks as tasks_count' => $taskUsage,
        ])->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id));
    }

    private function applyProjectUsageFilters(Builder $query, array $filters, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->whereBetween('created_at', [$from, $to])
            ->when($filters['user_id'] ?? null, fn (Builder $builder, $id) => $builder->where('owner_id', $id))
            ->when($filters['project_id'] ?? null, fn (Builder $builder, $id) => $builder->whereKey($id));
    }

    private function applyTaskUsageFilters(Builder $query, array $filters, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->whereBetween('created_at', [$from, $to])
            ->when($filters['user_id'] ?? null, fn (Builder $builder, $id) => $builder->where(fn (Builder $tasks) => $tasks->where('owner_id', $id)->orWhere('assigned_user_id', $id)))
            ->when($filters['project_id'] ?? null, fn (Builder $builder, $id) => $builder->where('project_id', $id))
            ->when($filters['task_status'] ?? null, fn (Builder $builder, $status) => $builder->where('status', $status));
    }

    private function categoryDistribution(Collection $categories, string $column, string $direction = 'desc'): array
    {
        return $categories->map(fn (Category $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'status' => $category->trashed() ? 'archived' : 'active',
            'color' => $category->color,
            'value' => (int) $category->{$column},
        ])->sortBy('value', SORT_REGULAR, $direction === 'desc')->values()->all();
    }

    private function categorySummary(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'status' => $category->trashed() ? 'archived' : 'active',
            'color' => $category->color,
            'projects' => (int) $category->projects_count,
            'tasks' => (int) $category->tasks_count,
        ];
    }

    private function assigneeDistribution(Builder $query): array
    {
        $counts = (clone $query)->whereNotNull('assigned_user_id')->selectRaw('assigned_user_id, COUNT(*) as total')
            ->groupBy('assigned_user_id')->orderByDesc('total')->limit(8)->get();
        $users = User::withTrashed()->whereIn('id', $counts->pluck('assigned_user_id'))->get(['id', 'name', 'deleted_at'])->keyBy('id');

        return $counts->map(fn ($row) => [
            'label' => ($users->get($row->assigned_user_id)?->name ?? 'Unavailable user').($users->get($row->assigned_user_id)?->deleted_at ? ' (archived account)' : ''),
            'value' => (int) $row->total,
        ])->values()->all();
    }

    private function growthPercent(int $current, int $previous): ?float
    {
        return $previous === 0 ? null : round((($current - $previous) / $previous) * 100, 1);
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        return $denominator === 0 ? null : round(($numerator / $denominator) * 100, 1);
    }
}
