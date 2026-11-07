<?php

namespace App\Services\Api\V1\Admin;

use App\Models\User;
use App\Models\UserActivityLog;

class UserActivityLogger
{
    public function record(string $event, string $description, ?User $subject = null, ?User $actor = null, array $context = []): UserActivityLog
    {
        return UserActivityLog::create([
            'user_id' => $subject?->id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'description' => $description,
            'context' => $context ?: null,
            'created_at' => now(),
        ]);
    }
}
