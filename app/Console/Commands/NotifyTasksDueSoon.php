<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\Api\V1\Notifications\NotificationService;
use Illuminate\Console\Command;

class NotifyTasksDueSoon extends Command
{
    protected $signature = 'planora:notify-tasks-due-soon';

    protected $description = 'Create idempotent notifications for active tasks due tomorrow.';

    public function handle(NotificationService $notifications): int
    {
        $tomorrow = now()->addDay()->toDateString();

        Task::query()
            ->where('due_date', $tomorrow)
            ->where('status', '!=', 'completed')
            ->whereNotNull('assigned_user_id')
            ->with('assignedUser')
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use ($notifications) {
                foreach ($tasks as $task) {
                    if ($task->assignedUser) $notifications->taskDueSoon($task->id);
                }
            });

        return self::SUCCESS;
    }
}
