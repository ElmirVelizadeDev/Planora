<?php

namespace App\Http\Resources\Api\V1\Admin\Categories;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'status' => $this->trashed() ? 'archived' : 'active',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'projects_count' => $this->whenCounted('projects'),
            'tasks_count' => $this->whenCounted('tasks'),
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
