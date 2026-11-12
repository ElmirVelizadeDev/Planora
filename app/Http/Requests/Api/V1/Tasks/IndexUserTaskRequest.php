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
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'due' => ['nullable', Rule::in(['today', 'upcoming', 'overdue'])],
            'due_after' => ['nullable', 'date_format:Y-m-d'],
            'due_before' => ['nullable', 'date_format:Y-m-d'],
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
            if ($this->filled('start_date') && $this->filled('end_date') && $this->input('start_date') > $this->input('end_date')) {
                $validator->errors()->add('start_date', 'The start date must be before or equal to the end date.');
            }
        });
    }
}
