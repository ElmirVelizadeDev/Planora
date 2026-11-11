<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectDemoSeeder extends Seeder
{
    private const PROJECTS = [
        ['name' => 'Customer onboarding refresh', 'description' => 'Improve the first-week experience with clearer setup steps, useful guidance, and a smoother handoff.', 'status' => 'active', 'start' => -18, 'due' => 24],
        ['name' => 'Quarterly research library', 'description' => 'Organize customer research and make validated product insights easier to discover.', 'status' => 'planned', 'start' => 12, 'due' => 62],
        ['name' => 'Workspace accessibility review', 'description' => 'Review core workspace flows and resolve the highest-impact accessibility findings.', 'status' => 'on_hold', 'start' => -35, 'due' => 9],
        ['name' => 'Spring release coordination', 'description' => 'Coordinate the completed spring release, record outcomes, and capture follow-up opportunities.', 'status' => 'completed', 'start' => -80, 'due' => -20],
    ];

    private const TASKS = [
        ['title' => 'Confirm scope and success measures', 'status' => 'completed', 'priority' => 'high', 'due' => -5],
        ['title' => 'Prepare the first delivery milestone', 'status' => 'in_progress', 'priority' => 'medium', 'due' => 4],
        ['title' => 'Review stakeholder feedback', 'status' => 'pending', 'priority' => 'urgent', 'due' => 9],
        ['title' => 'Publish the project handoff notes', 'status' => 'pending', 'priority' => 'low', 'due' => 16],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('ProjectDemoSeeder is restricted to the local environment.');
        }

        DB::transaction(function (): void {
            $users = User::query()->where('role', 'user')->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
            foreach ($users as $user) {
                foreach (self::PROJECTS as $projectIndex => $template) {
                    $name = sprintf('[Planora Seed U%d P%02d] %s', $user->id, $projectIndex + 1, $template['name']);
                    $project = Project::query()->firstOrNew(['owner_id' => $user->id, 'name' => $name]);
                    $project->fill([
                        'name' => $name, 'description' => $template['description'], 'status' => $template['status'],
                        'start_date' => now()->addDays($template['start'])->toDateString(),
                        'due_date' => now()->addDays($template['due'])->toDateString(), 'owner_id' => $user->id,
                    ])->save();

                    foreach (self::TASKS as $taskIndex => $taskTemplate) {
                        $title = sprintf('[Planora Seed P%d T%02d] %s', $project->id, $taskIndex + 1, $taskTemplate['title']);
                        $task = Task::withTrashed()->firstOrNew(['title' => $title]);
                        $taskStatus = match ($template['status']) {
                            'completed' => 'completed',
                            'planned' => 'pending',
                            default => $taskTemplate['status'],
                        };
                        $taskDueDays = match ($template['status']) {
                            'completed' => -abs($taskTemplate['due']),
                            'planned' => $template['start'] + (($taskIndex + 1) * 7),
                            default => $taskTemplate['due'],
                        };
                        $task->fill([
                            'title' => $title, 'description' => 'Local development sample task for '.$name.'.',
                            'status' => $taskStatus, 'priority' => $taskTemplate['priority'],
                            'due_date' => now()->addDays($taskDueDays)->toDateString(), 'project_id' => $project->id,
                            'owner_id' => $user->id, 'assigned_user_id' => $user->id, 'created_by' => $user->id,
                        ])->save();
                        if ($task->trashed()) $task->restore();
                    }
                }
            }
        });

        $this->command?->info('Created or refreshed four marked projects and four project tasks per active normal user.');
    }
}
