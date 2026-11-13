<?php

namespace App\Http\Resources\Api\V1\Admin\Users;

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
            'context' => $this->safeContext($this->context),
            'created_at' => $this->created_at,
        ];
    }

    private function safeContext(?array $context): ?array
    {
        if (! is_array($context)) {
            return null;
        }

        $safe = [];
        foreach ($context as $key => $value) {
            if (preg_match('/password|passphrase|otp|code|token|secret|cookie|authorization|remember|recovery|session|credential|csrf|api[_-]?key|private[_-]?key/i', (string) $key) === 1
                || in_array(strtolower((string) $key), ['headers', 'cookies'], true)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->safeContext($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
