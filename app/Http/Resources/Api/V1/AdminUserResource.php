<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->profile_photo,
            'role' => $this->role,
            'moderator_permissions' => $this->role === 'moderator' ? ($this->moderator_permissions ?? []) : [],
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'last_login_at' => $this->last_login_at,
            'status_reason' => $this->status_reason,
            'status_changed_at' => $this->status_changed_at,
            'status_changed_by' => $this->whenLoaded('statusChangedBy', fn () => $this->statusChangedBy ? [
                'id' => $this->statusChangedBy->id,
                'name' => $this->statusChangedBy->name,
            ] : null),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
