<?php

namespace App\Http\Requests\Api\V1\Admin\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Category;
use App\Models\Task;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $availableUser = fn () => Rule::exists('users', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('role', 'user')->where('status', 'active'));

        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'required', Rule::in(['pending', 'in_progress', 'completed'])],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'owner_id' => ['sometimes', 'required', 'integer', $availableUser()],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', $availableUser()],
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('category_id')) {
                return;
            }

            $task = $this->route('task');
            if (! $task instanceof Task) {
                return;
            }

            $categoryId = (int) $this->input('category_id');
            if ($categoryId !== (int) $task->category_id && ! Category::query()->whereKey($categoryId)->exists()) {
                $validator->errors()->add('category_id', 'Select an active category.');
            }
        });
    }
}
