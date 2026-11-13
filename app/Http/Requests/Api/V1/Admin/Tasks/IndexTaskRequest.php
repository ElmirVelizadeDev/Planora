<?php

namespace App\Http\Requests\Api\V1\Admin\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_before' => ['nullable', 'date_format:Y-m-d'],
            'due_after' => ['nullable', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'due_asc', 'due_desc', 'priority_asc', 'priority_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('due_after') && $this->filled('due_before') && $this->input('due_after') > $this->input('due_before')) {
                $validator->errors()->add('due_after', 'The due-after date must be before or equal to the due-before date.');
            }
            if ($this->filled('start_date') && $this->filled('end_date') && $this->input('start_date') > $this->input('end_date')) {
                $validator->errors()->add('start_date', 'The start date must be before or equal to the end date.');
            }
        });
    }
}
