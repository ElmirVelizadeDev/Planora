<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserTasksDemoSeeder extends Seeder
{
    /** @var array<int, array{title: string, description: string, status: string, priority: string, due_days: int, category: string}> */
    private const TASKS = [
        ['title' => 'Review the weekly project roadmap', 'description' => 'Confirm this week’s milestones, owners, and dependencies before work begins.', 'status' => 'pending', 'priority' => 'high', 'due_days' => 2, 'category' => 'Web Development'],
        ['title' => 'Prepare client handoff notes', 'description' => 'Summarize agreed decisions, open questions, and clear next steps for the client handoff.', 'status' => 'in_progress', 'priority' => 'medium', 'due_days' => 5, 'category' => 'Documentation'],
        ['title' => 'Close out sprint action items', 'description' => 'Verify completed sprint follow-ups and record any remaining engineering work.', 'status' => 'completed', 'priority' => 'low', 'due_days' => -2, 'category' => 'Bug Fixes'],
        ['title' => 'Confirm release quality checks', 'description' => 'Run the agreed release checks and document any issue that needs follow-up.', 'status' => 'pending', 'priority' => 'urgent', 'due_days' => 4, 'category' => 'DevOps'],
        ['title' => 'Organize research and reference files', 'description' => 'Group current product research and make the most useful references easy to find.', 'status' => 'pending', 'priority' => 'medium', 'due_days' => 10, 'category' => 'Research'],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('UserTasksDemoSeeder is restricted to the local environment.');
        }

        DB::transaction(function (): void {
            $users = User::query()
                ->where('role', 'user')
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($users as $user) {
                foreach (self::TASKS as $index => $template) {
                    $number = $index + 1;
                    $title = sprintf('[Planora Seed U%d T%02d] %s', $user->id, $number, $template['title']);
                    $taskMarker = sprintf('[Planora Seed U%d T%02d]%%', $user->id, $number);
                    $task = Task::withTrashed()
                        ->where('owner_id', $user->id)
                        ->where('title', 'like', $taskMarker)
                        ->orderBy('id')
                        ->first() ?? new Task(['owner_id' => $user->id]);
                    $categoryId = Category::query()->where('name', $template['category'])->value('id');
                    $categoryId = $task->exists && $task->category_id !== null ? $task->category_id : $categoryId;

                    $task->fill([
                        'title' => $title,
                        'description' => $template['description'].' This is a local development seed record.',
                        'status' => $template['status'],
                        'priority' => $template['priority'],
                        'due_date' => now()->addDays($template['due_days'])->toDateString(),
                        'owner_id' => $user->id,
                        'project_id' => null,
                        'assigned_user_id' => null,
                        'created_by' => $user->id,
                        'category_id' => $categoryId,
                    ]);
                    $task->save();

                    if ($task->trashed()) {
                        $task->restore();
                    }
                }
            }
        });

        $this->command?->info('Created or refreshed five marked demo tasks for each active, non-deleted user account.');
    }
}
