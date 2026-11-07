<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email] : null),
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? ['id' => $this->actor->id, 'name' => $this->actor->name] : null),
            'event' => $this->event,
            'description' => $this->description,
            'context' => $this->context,
            'created_at' => $this->created_at,
        ];
    }
}
