<?php

namespace App\Http\Resources\Api\V1\Shared\Projects;

use App\Http\Resources\Api\V1\Shared\Categories\CategorySummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'category' => $this->whenLoaded('category', fn () => $this->category
                ? CategorySummaryResource::make($this->category)->resolve($request)
                : null),
            'owner' => $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->name, 'email' => $this->owner->email, 'avatar' => $this->owner->avatar_url] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'tasks_count' => $this->whenCounted('tasks'),
        ];
    }
}
