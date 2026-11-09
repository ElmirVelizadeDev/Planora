<?php

namespace App\Http\Requests\Api\V1\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'user';
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'due' => ['nullable', Rule::in(['today', 'upcoming', 'overdue'])],
            'due_after' => ['nullable', 'date_format:Y-m-d'],
            'due_before' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'due_asc', 'due_desc', 'priority_asc', 'priority_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
