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
            "task-assigned:task:{$task->id}:recipient:{$recipient->id}:event:".($eventId ?: 'initial'));
    }

    public function taskStatusChanged(Task $task, User $actor, string $status, ?string $eventId = null, ?string $oldStatus = null): void
    {
        $completed = $status === 'completed';
        $reopened = $oldStatus === 'completed' && ! $completed;
        $type = $completed ? 'task.completed' : ($reopened ? 'task.reopened' : 'task.status_changed');
        $title = $completed ? 'Task completed' : ($reopened ? 'Task reopened' : 'Task status changed');
        $description = $completed
            ? "{$actor->name} completed “{$task->title}”."
            : ($reopened ? "{$actor->name} reopened “{$task->title}”." : "{$actor->name} changed the status of “{$task->title}”.");

        $recipientIds = in_array($actor->role, ['admin', 'moderator'], true)
            ? array_unique([$task->owner_id, $task->assigned_user_id])
            : ((int) $task->assigned_user_id === (int) $actor->id ? [$task->owner_id] : []);

        foreach ($recipientIds as $recipientId) {
            if (! $recipientId || (int) $recipientId === (int) $actor->id) continue;
            $recipient = User::query()->whereKey($recipientId)->where('role', 'user')->where('status', 'active')->first();
            if (! $recipient) continue;

            $this->create($recipient, $type, $title, $description, 'task', $task->id,
                "task-status:task:{$task->id}:recipient:{$recipient->id}:status:{$status}:event:".($eventId ?: 'initial'));
        }
    }

    public function taskUpdated(Task $task, User $actor, array $changedFields, ?string $eventId = null): void
    {
        $recipient = $task->assignedUser;
        $significantFields = array_values(array_intersect($changedFields, ['title', 'priority', 'due_date']));
        if (! $recipient || ! $significantFields || $recipient->trashed() || $recipient->role !== 'user'
            || $recipient->status !== 'active' || (int) $recipient->id === (int) $actor->id) return;

        $this->create($recipient, 'task.updated', 'Task details updated',
            "{$actor->name} updated “{$task->title}”.", 'task', $task->id,
            "task-updated:task:{$task->id}:recipient:{$recipient->id}:fields:".implode(',', $significantFields).':event:'.($eventId ?: 'initial'));
    }

    public function adminTaskCreated(Task $task, User $actor, ?string $eventId = null): void
    {
        User::query()->where('role', 'admin')->where('status', 'active')->whereKeyNot($actor->id)
            ->each(function (User $admin) use ($task, $actor, $eventId) {
                $this->create($admin, 'admin.task_created', 'New task created',
                    "{$actor->name} created “{$task->title}”.", 'task', $task->id,
                    "admin-task-created:task:{$task->id}:event:".($eventId ?: 'created').":recipient:{$admin->id}");
            });
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

        if ($actor instanceof User) $query->whereKeyNot($actor->id);

        $query->each(function (User $admin) use ($user) {
            $this->create($admin, 'admin.user_registered', 'New user registered',
                "{$user->name} created a Planora account.", null, null,
                "user-registered:{$admin->id}:{$user->id}");
        });
    }

    public function accountStatusChanged(User $subject, User $actor, string $oldStatus, string $status, ?string $eventId = null): void
    {
        $label = ucfirst($status);
        $event = match ($status) {
            'banned' => ['admin.account_banned', 'User account banned', 'banned'],
            'inactive' => ['admin.account_deactivated', 'User account deactivated', 'deactivated'],
            'active' => ['admin.account_reactivated', 'User account reactivated', 'reactivated'],
            default => ['admin.account_status_changed', 'User account status changed', 'updated'],
        };

        $this->notifyOtherAdmins($subject, $actor, $event[0], $event[1],
            "{$actor->name} {$event[2]} the account for {$subject->name}.", $eventId);

        if ($subject->role === 'user') {
            $this->create($subject, 'account.status_changed', 'Account status updated',
                "Your Planora account status changed from ".ucfirst($oldStatus)." to {$label}.", null, null,
                "account-status:user:{$subject->id}:event:".($eventId ?: "{$oldStatus}-{$status}"));
        }
    }

    public function accountRoleChanged(User $subject, User $actor, string $oldRole, string $role, ?string $eventId = null): void
    {
        $this->notifyOtherAdmins($subject, $actor, 'admin.account_role_changed', 'User role changed',
            "{$actor->name} changed {$subject->name} from ".ucfirst($oldRole)." to ".ucfirst($role).'.', $eventId);
    }

    public function moderatorPermissionsChanged(User $subject, User $actor, ?string $eventId = null): void
    {
        $this->notifyOtherAdmins($subject, $actor, 'admin.moderator_permissions_changed', 'Moderator permissions changed',
            "{$actor->name} updated moderator permissions for {$subject->name}.", $eventId);
    }

    public function accountRestored(User $subject, User $actor, ?string $eventId = null): void
    {
        $this->notifyOtherAdmins($subject, $actor, 'admin.account_restored', 'User account restored',
            "{$actor->name} restored {$subject->name}'s account.", $eventId);
        if ($subject->role === 'user') {
            $this->create($subject, 'account.restored', 'Account restored',
                'Your Planora account has been restored and is active again.', null, null,
                "account-restored:user:{$subject->id}:event:".($eventId ?: (string) $subject->updated_at));
        }
    }

    public function accountDeleted(User $subject, User $actor, ?string $eventId = null): void
    {
        $this->notifyOtherAdmins($subject, $actor, 'admin.account_deleted', 'User account deleted',
            "{$actor->name} moved {$subject->name}'s account to deleted recovery.", $eventId);
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

    private function notifyOtherAdmins(User $subject, User $actor, string $type, string $title, string $description, ?string $eventId): void
    {
        User::query()->where('role', 'admin')->where('status', 'active')->whereKeyNot($actor->id)
            ->each(function (User $admin) use ($subject, $type, $title, $description, $eventId) {
                $this->create($admin, $type, $title, $description, null, null,
                    "admin-account-change:{$type}:subject:{$subject->id}:event:".($eventId ?: (string) $subject->updated_at).":recipient:{$admin->id}");
            });
    }
}
