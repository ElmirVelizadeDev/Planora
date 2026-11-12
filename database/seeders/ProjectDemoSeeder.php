<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectDemoSeeder extends Seeder
{
    /** @var array<int, array{name: string, description: string, status: string, start: int, due: int, category: string, tasks: array<int, array{title: string, description: string, status: string, priority: string, due: int}>}> */
    private const PROJECTS = [
        [
            'name' => 'Company Website',
            'description' => 'Build a fast, accessible company website that explains the product clearly and turns qualified visits into customer conversations.',
            'status' => 'active', 'start' => -20, 'due' => 32, 'category' => 'Web Development',
            'tasks' => [
                ['title' => 'Build responsive navigation', 'description' => 'Implement accessible desktop and mobile navigation with clear paths to key pages.', 'status' => 'completed', 'priority' => 'high', 'due' => -8],
                ['title' => 'Implement homepage layout', 'description' => 'Create the responsive homepage sections and connect them to the approved content.', 'status' => 'in_progress', 'priority' => 'high', 'due' => 5],
                ['title' => 'Add contact form validation', 'description' => 'Validate required fields and provide clear success and error feedback.', 'status' => 'pending', 'priority' => 'medium', 'due' => 13],
                ['title' => 'Test mobile responsiveness', 'description' => 'Check key page layouts and interactions across common mobile viewport sizes.', 'status' => 'pending', 'priority' => 'high', 'due' => 21],
            ],
        ],
        [
            'name' => 'Mobile App Redesign',
            'description' => 'Refresh the mobile product experience with a consistent component system and clearer account and dashboard flows.',
            'status' => 'active', 'start' => -12, 'due' => 45, 'category' => 'UI/UX Design',
            'tasks' => [
                ['title' => 'Create login screen design', 'description' => 'Design a clear sign-in flow with accessible labels, validation states, and recovery guidance.', 'status' => 'completed', 'priority' => 'medium', 'due' => -4],
                ['title' => 'Prepare dashboard wireframes', 'description' => 'Map the main mobile dashboard hierarchy and prioritize the most frequent actions.', 'status' => 'in_progress', 'priority' => 'high', 'due' => 6],
                ['title' => 'Design reusable UI components', 'description' => 'Define reusable mobile patterns for buttons, forms, cards, and navigation.', 'status' => 'pending', 'priority' => 'medium', 'due' => 19],
                ['title' => 'Review accessibility and typography', 'description' => 'Check contrast, text sizing, focus behavior, and readable content hierarchy.', 'status' => 'pending', 'priority' => 'high', 'due' => 30],
            ],
        ],
        [
            'name' => 'Database Optimization',
            'description' => 'Improve the performance and reliability of core application data queries while preserving existing records and behavior.',
            'status' => 'planned', 'start' => 8, 'due' => 52, 'category' => 'Database',
            'tasks' => [
                ['title' => 'Analyze slow SQL queries', 'description' => 'Profile frequently used queries and document the highest-impact performance bottlenecks.', 'status' => 'pending', 'priority' => 'high', 'due' => 15],
                ['title' => 'Review database relationships', 'description' => 'Validate key relationships and identify safe opportunities to reduce unnecessary reads.', 'status' => 'pending', 'priority' => 'medium', 'due' => 23],
                ['title' => 'Add approved database indexes', 'description' => 'Apply reviewed indexes that support measured query patterns and existing constraints.', 'status' => 'pending', 'priority' => 'urgent', 'due' => 35],
                ['title' => 'Test query performance', 'description' => 'Compare representative query timings before and after the approved optimizations.', 'status' => 'pending', 'priority' => 'high', 'due' => 46],
            ],
        ],
        [
            'name' => 'Product Launch',
            'description' => 'Coordinate the next product release with a focused campaign, launch content, and clear readiness checkpoints.',
            'status' => 'on_hold', 'start' => -6, 'due' => 28, 'category' => 'Marketing',
            'tasks' => [
                ['title' => 'Analyze the target audience', 'description' => 'Summarize priority audiences, their needs, and the product value most relevant to them.', 'status' => 'completed', 'priority' => 'medium', 'due' => -2],
                ['title' => 'Prepare social media campaign', 'description' => 'Draft a coordinated set of launch messages and channel-specific publishing milestones.', 'status' => 'in_progress', 'priority' => 'high', 'due' => 7],
                ['title' => 'Create launch content', 'description' => 'Prepare concise launch copy and supporting product education content for review.', 'status' => 'pending', 'priority' => 'high', 'due' => 16],
                ['title' => 'Complete launch readiness checklist', 'description' => 'Confirm owners, approvals, support readiness, and release-day communications.', 'status' => 'pending', 'priority' => 'urgent', 'due' => 24],
            ],
        ],
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
                    $projectMarker = sprintf('[Planora Seed U%d P%02d]%%', $user->id, $projectIndex + 1);
                    $project = Project::query()
                        ->where('owner_id', $user->id)
                        ->where('name', 'like', $projectMarker)
                        ->orderBy('id')
                        ->first() ?? new Project(['owner_id' => $user->id]);
                    $projectCategoryId = Category::query()->where('name', $template['category'])->value('id');
                    $categoryId = $project->exists && $project->category_id !== null ? $project->category_id : $projectCategoryId;
                    $project->fill([
                        'name' => $name,
                        'description' => $template['description'],
                        'status' => $template['status'],
                        'start_date' => now()->addDays($template['start'])->toDateString(),
                        'due_date' => now()->addDays($template['due'])->toDateString(),
                        'owner_id' => $user->id,
                        'category_id' => $categoryId,
                    ])->save();

                    foreach ($template['tasks'] as $taskIndex => $taskTemplate) {
                        $taskMarker = sprintf('[Planora Seed P%d T%02d]%%', $project->id, $taskIndex + 1);
                        $title = sprintf('[Planora Seed P%d T%02d] %s', $project->id, $taskIndex + 1, $taskTemplate['title']);
                        $task = Task::withTrashed()
                            ->where('project_id', $project->id)
                            ->where('title', 'like', $taskMarker)
                            ->orderBy('id')
                            ->first() ?? new Task();
                        $projectCategoryIsActive = $project->category_id !== null
                            && Category::query()->whereKey($project->category_id)->exists();
                        $taskCategoryId = $projectCategoryIsActive
                            ? $project->category_id
                            : Category::query()->where('name', $template['category'])->value('id');
                        $taskCategoryId = $task->exists && $task->category_id !== null ? $task->category_id : $taskCategoryId;
                        $taskDueDays = $template['status'] === 'completed' ? -abs($taskTemplate['due']) : $taskTemplate['due'];

                        $task->fill([
                            'title' => $title,
                            'description' => $taskTemplate['description'],
                            'status' => $template['status'] === 'completed' ? 'completed' : $taskTemplate['status'],
                            'priority' => $taskTemplate['priority'],
                            'due_date' => now()->addDays($taskDueDays)->toDateString(),
                            'project_id' => $project->id,
                            'owner_id' => $user->id,
                            'assigned_user_id' => $user->id,
                            'created_by' => $user->id,
                            'category_id' => $taskCategoryId,
                        ])->save();
                        if ($task->trashed()) {
                            $task->restore();
                        }
                    }
                }
            }
        });

        $this->command?->info('Created or refreshed four marked projects and four purpose-matched project tasks per active normal user.');
    }
}
