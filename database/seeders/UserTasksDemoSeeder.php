<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserTasksDemoSeeder extends Seeder
{
    /** @var array<int, array{title: string, description: string, status: string, priority: string, due_days: int}> */
    private const TASKS = [
        ['title' => 'Review the weekly project roadmap', 'description' => 'Confirm this week’s milestones and flag any work that needs to move.', 'status' => 'pending', 'priority' => 'high', 'due_days' => 2],
        ['title' => 'Prepare client handoff notes', 'description' => 'Summarize decisions, open questions, and the next steps for the client handoff.', 'status' => 'in_progress', 'priority' => 'medium', 'due_days' => 5],
        ['title' => 'Close out sprint action items', 'description' => 'Review the sprint checklist and record the completed follow-up work.', 'status' => 'completed', 'priority' => 'low', 'due_days' => -2],
        ['title' => 'Confirm delivery milestones', 'description' => 'Check the delivery plan with the team and update any overdue milestone.', 'status' => 'pending', 'priority' => 'urgent', 'due_days' => -3],
        ['title' => 'Organize research and reference files', 'description' => 'Group the current project research and make the key references easy to find.', 'status' => 'pending', 'priority' => 'medium', 'due_days' => 10],
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
                    $task = Task::withTrashed()->firstOrNew([
                        'owner_id' => $user->id,
                        'title' => $title,
                    ]);

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
