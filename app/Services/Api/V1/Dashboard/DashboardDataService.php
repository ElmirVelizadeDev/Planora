<?php

namespace App\Services\Api\V1\Dashboard;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\Category;

class DashboardDataService
{
    public function for(User $viewer): array
    {
        $admin = $viewer->role === 'admin';
        $canUsers = $admin || $viewer->hasManagementPermission('users.view');
        $canActivity = $admin || $viewer->hasManagementPermission('activity.view');
        $canCategories = $admin || $viewer->hasManagementPermission('categories.view');
        $canProjects = $admin || $viewer->hasManagementPermission('projects.view');
        $canTasks = $admin || $viewer->hasManagementPermission('tasks.view');
        $canReports = $admin || $viewer->hasManagementPermission('reports.view');
        $data = ['sections' => [
            'users' => $canUsers, 'activity' => $canActivity, 'categories' => $canCategories,
            'projects' => $canProjects, 'tasks' => $canTasks, 'reports' => $canReports,
        ]];

        if ($canUsers) {
            $users = User::query();
            if (! $admin) $users->where('role', '!=', 'admin');
            $counts = (clone $users)->selectRaw("COUNT(*) total, SUM(status = 'active') active, SUM(status = 'inactive') inactive, SUM(status = 'suspended') suspended, SUM(status = 'banned') banned, SUM(role = 'moderator') moderators")->toBase()->first();
            $data['users'] = [
                'total' => (int) $counts->total, 'active' => (int) $counts->active, 'inactive' => (int) $counts->inactive,
                'suspended' => (int) $counts->suspended, 'banned' => (int) $counts->banned,
                'moderators' => $admin ? User::query()->where('role', 'moderator')->count() : (int) $counts->moderators,
                'recent_registrations' => (clone $users)->latest('created_at')->limit(5)->get(['id', 'name', 'email', 'role', 'status', 'created_at']),
            ];
        }

        if ($canActivity) {
            $logs = UserActivityLog::query()->with('actor:id,name')->latest('created_at');
            if (! $admin) {
                $logs->whereHas('user', fn ($user) => $user->where('role', '!=', 'admin'))
                    ->whereRaw("COALESCE(NULLIF(module, ''), CASE WHEN event LIKE 'auth.%' THEN 'Authentication' WHEN event LIKE 'category.%' THEN 'Categories' WHEN event LIKE 'project.%' THEN 'Projects' WHEN event LIKE 'task.%' THEN 'Tasks' WHEN event LIKE 'report.%' THEN 'Reports' WHEN event LIKE 'settings.%' THEN 'Settings' ELSE 'Users' END) IN (?, ?)", ['Users', 'Authentication']);
            }
            $data['activity'] = $logs->limit(6)->get(['id', 'event', 'description', 'actor_id', 'user_id', 'created_at'])->map(fn ($log) => [
                'id' => $log->id, 'event' => $log->event, 'description' => $log->description,
                'actor' => $log->actor?->name, 'created_at' => $log->created_at?->toIso8601String(),
            ]);
        }

        if ($canCategories) {
            $categoryCounts = Category::withTrashed()
                ->selectRaw('COUNT(*) total, SUM(deleted_at IS NULL) active, SUM(deleted_at IS NOT NULL) archived')
                ->toBase()->first();
            $data['categories'] = [
                'total' => (int) $categoryCounts->total,
                'active' => (int) $categoryCounts->active,
                'archived' => (int) $categoryCounts->archived,
            ];
        }

        if ($canProjects) {
            $projectCounts = Project::query()->selectRaw("COUNT(*) total, SUM(status = 'planned') planned, SUM(status = 'active') active, SUM(status = 'completed') completed, SUM(status = 'on_hold') on_hold")->toBase()->first();
            $data['projects'] = ['total' => (int) $projectCounts->total, 'planned' => (int) $projectCounts->planned, 'active' => (int) $projectCounts->active, 'completed' => (int) $projectCounts->completed, 'on_hold' => (int) $projectCounts->on_hold];
        }

        if ($canTasks) {
            $taskCounts = Task::query()->selectRaw("COUNT(*) total, SUM(status = 'pending') pending, SUM(status = 'in_progress') in_progress, SUM(status = 'completed') completed")->toBase()->first();
            $data['tasks'] = [
                'total' => (int) $taskCounts->total, 'pending' => (int) $taskCounts->pending,
                'in_progress' => (int) $taskCounts->in_progress, 'completed' => (int) $taskCounts->completed,
                'overdue' => Task::query()->where('status', '!=', 'completed')->whereNotNull('due_date')->whereDate('due_date', '<', today()->toDateString())->count(),
            ];
        }

        return $data;
    }
}
