<?php

namespace App\Services\Api\V1\Admin;

use App\Models\Category;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActivityLog;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserActivityLogger
{
    private const SENSITIVE_KEY_PATTERN = '/password|passphrase|otp|code|token|secret|cookie|authorization|remember|recovery|session|credential|csrf|api[_-]?key|private[_-]?key/i';

    public function record(
        string $event,
        string $description,
        ?Model $subject = null,
        ?User $actor = null,
        array $context = [],
        ?string $module = null,
        string $status = 'success',
        ?array $oldValues = null,
        ?array $newValues = null,
    ): UserActivityLog {
        $actor ??= $this->request()?->user();
        $request = $this->request();
        $actor = $actor instanceof User ? $actor : null;
        $subjectUser = $subject instanceof User ? $subject : null;
        $requestId = $request?->attributes->get('request_id');
        $requestId = is_string($requestId) && Str::isUuid($requestId) ? $requestId : null;
        $userAgent = $request?->userAgent();

        return UserActivityLog::create([
            'event_id' => (string) Str::uuid(),
            'user_id' => $subjectUser?->id,
            'actor_id' => $actor?->id,
            'event' => Str::limit($event, 80, ''),
            'description' => Str::limit($description, 500, ''),
            'module' => $module ?? $this->moduleFor($event),
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? $this->subjectLabel($subject) : null,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'actor_role' => $actor?->role,
            'context' => $this->sanitize($context) ?: null,
            'old_values' => $oldValues !== null ? ($this->sanitize($oldValues) ?: null) : null,
            'new_values' => $newValues !== null ? ($this->sanitize($newValues) ?: null) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => is_string($userAgent) ? Str::limit($this->cleanText($userAgent), 500, '') : null,
            'request_method' => $request?->method() ? Str::limit($request->method(), 10, '') : null,
            'request_path' => $request ? Str::limit($this->cleanText('/'.$request->path()), 500, '') : null,
            'request_id' => $requestId,
            'status' => in_array($status, ['success', 'failure'], true) ? $status : 'success',
            'created_at' => now(),
        ]);
    }

    public function recordChange(
        string $event,
        string $description,
        Model $subject,
        ?User $actor,
        array $oldValues,
        array $newValues,
        array $context = [],
        ?string $module = null,
    ): ?UserActivityLog {
        $oldValues = $this->sanitize($oldValues);
        $newValues = $this->sanitize($newValues);
        if ($oldValues === $newValues) {
            return null;
        }

        return $this->record($event, $description, $subject, $actor, $context, $module, 'success', $oldValues, $newValues);
    }

    private function moduleFor(string $event): string
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

    private function subjectLabel(Model $subject): ?string
    {
        $label = match (true) {
            $subject instanceof User, $subject instanceof Project, $subject instanceof Category => $subject->name,
            $subject instanceof Task => $subject->title,
            default => $subject->getAttribute('name') ?? $subject->getAttribute('title'),
        };

        return is_string($label) && trim($label) !== '' ? Str::limit($this->cleanText($label), 190, '') : null;
    }

    private function sanitize(array $values): array
    {
        $safe = [];
        foreach ($values as $key => $value) {
            $key = (string) $key;
            if (preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1 || in_array(strtolower($key), ['headers', 'cookies', 'session'], true)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
            } elseif ($value instanceof BackedEnum) {
                $safe[$key] = $value->value;
            } elseif ($value instanceof DateTimeInterface) {
                $safe[$key] = $value->format(DateTimeInterface::ATOM);
            } elseif ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
                $safe[$key] = $value;
            } elseif (is_string($value)) {
                $safe[$key] = Str::limit($this->cleanText($value), 2000, '');
            }
        }

        return $safe;
    }

    private function cleanText(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    }

    private function request(): ?Request
    {
        return app()->bound('request') ? request() : null;
    }
}
