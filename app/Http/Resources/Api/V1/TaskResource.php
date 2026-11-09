<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'owner' => $this->owner ? $this->userSummary($this->owner) : null,
            'assigned_user' => $this->assignedUser ? $this->userSummary($this->assignedUser) : null,
            'created_by' => $this->creator ? $this->userSummary($this->creator) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function userSummary($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url,
        ];
    }
}
