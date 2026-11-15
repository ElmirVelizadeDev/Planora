<?php

namespace App\Http\Resources\Api\V1\User\Profile;

use Illuminate\Http\Resources\Json\JsonResource;

class CurrentUserOverviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'projects' => [
                'total' => (int) data_get($this->resource, 'projects.total', 0),
                'completed' => (int) data_get($this->resource, 'projects.completed', 0),
            ],
            'tasks' => [
                'total' => (int) data_get($this->resource, 'tasks.total', 0),
                'completed' => (int) data_get($this->resource, 'tasks.completed', 0),
                'pending' => (int) data_get($this->resource, 'tasks.pending', 0),
            ],
        ];
    }
}
