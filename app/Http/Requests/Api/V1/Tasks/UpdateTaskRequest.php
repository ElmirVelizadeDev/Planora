<?php

namespace App\Http\Requests\Api\V1\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $availableUser = fn () => Rule::exists('users', 'id')->whereNull('deleted_at');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'required', Rule::in(['pending', 'in_progress', 'completed'])],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'owner_id' => ['sometimes', 'required', 'integer', $availableUser()],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', $availableUser()],
        ];
    }
}
