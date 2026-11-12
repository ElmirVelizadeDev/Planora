<?php

namespace App\Http\Requests\Api\V1\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Category;
use App\Models\Task;

class UpdateUserTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'user';
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'required', Rule::in(['pending', 'in_progress', 'completed'])],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'owner_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'assigned_user_id' => ['prohibited'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('category_id')) {
                return;
            }

            $task = Task::query()
                ->where('owner_id', $this->user()?->id)
                ->find($this->route('task'));
            if ($task && (int) $this->input('category_id') !== (int) $task->category_id
                && ! Category::query()->whereKey($this->input('category_id'))->exists()) {
                $validator->errors()->add('category_id', 'Select an active category.');
            }
        });
    }
}
