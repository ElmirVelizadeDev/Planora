<?php

namespace App\Http\Resources\Api\V1\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $recipient = $request->user();
        $task = $this->related_type === 'task' ? $this->relatedTask : null;
        $destination = null;

        if ($task && $recipient?->role === 'admin') {
            $destination = ['name' => 'admin.tasks.show', 'params' => ['taskId' => $task->id]];
        } elseif ($task && $recipient?->role === 'user'
            && ((int) $task->owner_id === (int) $recipient->id || (int) $task->assigned_user_id === (int) $recipient->id)) {
            $destination = ['name' => 'tasks.show', 'params' => ['taskId' => $task->id]];
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'destination' => $destination,
        ];
    }
}
