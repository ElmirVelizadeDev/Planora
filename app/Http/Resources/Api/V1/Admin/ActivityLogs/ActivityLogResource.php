<?php

namespace App\Http\Resources\Api\V1\Admin\ActivityLogs;

use App\Models\UserActivityLog;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var UserActivityLog $log */
        $log = $this->resource;
        $actor = $log->relationLoaded('actor') ? $log->actor : null;
        $subjectUser = $log->relationLoaded('user') ? $log->user : null;

        return [
            'id' => $log->id,
            'event_id' => $log->event_id,
            'action' => $log->event,
            'description' => $log->description,
            'module' => $log->module ?: $this->legacyModule($log->event),
            'status' => $log->status ?: 'success',
            'actor' => $log->actor_id || $log->actor_name || $log->actor_email ? [
                'id' => $log->actor_id,
                'name' => $log->actor_name ?? $actor?->name,
                'email' => $log->actor_email ?? $actor?->email,
                'role' => $log->actor_role ?? $actor?->role,
                'avatar' => $actor?->avatar_url,
                'deleted' => $actor?->trashed() ?? ($log->actor_id === null),
            ] : null,
            'target' => [
                'type' => $log->subject_type ?: ($subjectUser ? 'User' : null),
                'id' => $log->subject_id ?: $log->user_id,
                'label' => $log->subject_label ?? $subjectUser?->name,
            ],
            'old_values' => $this->safeProperties($log->old_values) ?: $this->legacyChange($log->context, 'from'),
            'new_values' => $this->safeProperties($log->new_values) ?: $this->legacyChange($log->context, 'to'),
            'properties' => $this->safeProperties($log->context),
            'request' => [
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'method' => $log->request_method,
                'path' => $log->request_path,
                'request_id' => $log->request_id,
            ],
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function legacyModule(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'auth.') => 'Authentication',
            str_starts_with($event, 'category.') => 'Categories',
            str_starts_with($event, 'project.') => 'Projects',
            str_starts_with($event, 'task.') => 'Tasks',
            str_starts_with($event, 'report.') => 'Reports',
            str_starts_with($event, 'settings.') => 'Settings',
            default => 'Users',
        };
    }

    private function legacyChange(?array $context, string $key): ?array
    {
        if (! is_array($context) || ! array_key_exists($key, $context)) {
            return null;
        }

        return ['value' => $this->safeProperties(['value' => $context[$key]])['value'] ?? null];
    }

    private function safeProperties(?array $properties): ?array
    {
        if (! is_array($properties)) {
            return null;
        }

        $safe = [];
        foreach ($properties as $key => $value) {
            if (preg_match('/password|passphrase|otp|code|token|secret|cookie|authorization|remember|recovery|session|credential|csrf|api[_-]?key|private[_-]?key/i', (string) $key) === 1
                || in_array(strtolower((string) $key), ['headers', 'cookies', 'session'], true)) {
                continue;
            }
            if (is_array($value)) {
                $safe[$key] = $this->safeProperties($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
