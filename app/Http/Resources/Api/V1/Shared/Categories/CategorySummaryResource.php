<?php

namespace App\Http\Resources\Api\V1\Shared\Categories;

use Illuminate\Http\Resources\Json\JsonResource;

class CategorySummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'status' => $this->trashed() ? 'archived' : 'active',
        ];
    }
}
