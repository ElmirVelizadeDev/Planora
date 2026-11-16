<?php

namespace App\Services\Api\V1\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function paginate(User $recipient, array $filters): LengthAwarePaginator
    {
        $query = UserNotification::query()
            ->where('recipient_id', $recipient->id)
            ->with('relatedTask')
            ->latest('created_at')
            ->latest('id');

        if (($filters['filter'] ?? 'all') === 'unread') {
            $query->whereNull('read_at');
        }

        return $query->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function unreadCount(User $recipient): int
    {
        return UserNotification::query()->where('recipient_id', $recipient->id)->whereNull('read_at')->count();
    }

    public function markRead(User $recipient, int $notificationId): UserNotification
    {
        $notification = UserNotification::query()
            ->where('recipient_id', $recipient->id)
            ->with('relatedTask')
            ->findOrFail($notificationId);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification->refresh()->load('relatedTask');
    }

    public function markAllRead(User $recipient): int
    {
        return UserNotification::query()
            ->where('recipient_id', $recipient->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    public function taskAssigned(Task $task, User $recipient, User $actor, ?string $eventId = null): void
    {
        if ($recipient->trashed() || $recipient->role !== 'user' || $recipient->status !== 'active'
            || (int) $recipient->id === (int) $actor->id) return;

        $this->create($recipient, 'task.assigned', 'Task assigned to you',
            "{$actor->name} assigned “{$task->title}” to you.", 'task', $task->id,
            "task-assigned:task:{$task->id}:recipient:{$recipient->id}");
    }

    public function taskStatusChanged(Task $task, User $actor, string $status, ?string $eventId = null): void
    {
        if ((int) $task->assigned_user_id !== (int) $actor->id
            || ! $task->owner
            || $task->owner->trashed()
            || $task->owner->role !== 'user'
            || (int) $task->owner_id === (int) $actor->id
            || $task->owner->status !== 'active') return;

        $completed = $status === 'completed';
        $type = $completed ? 'task.completed' : 'task.status_changed';
        $title = $completed ? 'Task completed' : 'Task status changed';
        $description = $completed
            ? "{$actor->name} completed “{$task->title}”."
            : "{$actor->name} changed the status of “{$task->title}”.";

        $this->create($task->owner, $type, $title, $description, 'task', $task->id,
            "task-status:task:{$task->id}:owner:{$task->owner_id}:status:{$status}");
    }

    public function taskDueSoon(int $taskId): void
    {
        DB::transaction(function () use ($taskId): void {
            $task = Task::query()->lockForUpdate()->find($taskId);
            $tomorrow = now()->addDay()->toDateString();

            if (! $task || ($task->status?->value ?? $task->status) === 'completed'
                || ! $task->due_date || $task->due_date->toDateString() !== $tomorrow
                || ! $task->assigned_user_id) {
                return;
            }

            $recipient = User::query()->whereKey($task->assigned_user_id)
                ->where('role', 'user')->where('status', 'active')
                ->lockForUpdate()->first();

            if (! $recipient) return;

            $dueDate = $task->due_date->toDateString();
            $this->create($recipient, 'task.due_soon', 'Task due soon',
                "“{$task->title}” is due on {$task->due_date->format('M j')}.", 'task', $task->id,
                "task-due-soon:{$task->id}:{$recipient->id}:{$dueDate}");
        });
    }

    public function userRegistered(User $user, ?User $actor = null): void
    {
        $query = User::query()->where('role', 'admin')->where('status', 'active');

        if ($actor instanceof User) {
            $query->whereKeyNot($actor->id);
        } else {
            $primaryAdminId = User::query()->where('role', 'admin')->where('status', 'active')
                ->orderBy('id')->value('id');

            if (! empty($primaryAdminId)) {
                $query->whereKeyNot((int) $primaryAdminId);
            }
        }

        $query->each(function (User $admin) use ($user) {
            $this->create($admin, 'admin.user_registered', 'New user registered',
                "{$user->name} created a Planora account.", null, null,
                "user-registered:{$admin->id}:{$user->id}");
        });
    }

    public function maintenanceChanged(User $actor, array $before, array $after): void
    {
        $changeKey = hash('sha256', json_encode([
            'before' => $before,
            'after' => $after,
            'changed_at' => now()->format('U.u'),
        ]));

        User::query()
            ->where('role', 'admin')
            ->where('status', 'active')
            ->where('id', '<>', $actor->id)
            ->each(function (User $admin) use ($actor, $changeKey) {
                $this->create($admin, 'admin.maintenance_changed', 'Maintenance settings changed',
                    "{$actor->name} updated system maintenance settings.", null, null,
                    "maintenance-change:{$changeKey}:{$admin->id}");
            });
    }

    private function create(
        User $recipient,
        string $type,
        string $title,
        string $description,
        ?string $relatedType,
        ?int $relatedId,
        string $deduplicationKey,
    ): UserNotification {
        return UserNotification::query()->firstOrCreate(
            ['deduplication_key' => hash('sha256', $deduplicationKey)],
            [
                'recipient_id' => $recipient->id,
                'type' => $type,
                'title' => mb_substr($title, 0, 180),
                'description' => mb_substr($description, 0, 255),
                'related_type' => $relatedType,
                'related_id' => $relatedId,
            ],
        );
    }
}
