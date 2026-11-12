<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'created_at' => $this->created_at,
            'projects_count' => $this->projects_count,
            'tasks_count' => $this->tasks_count,
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'due_date' => $project->due_date?->format('Y-m-d'),
            ])->values()),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status?->value,
                'priority' => $task->priority?->value,
                'due_date' => $task->due_date?->format('Y-m-d'),
            ])->values()),
        ];
    }
}
