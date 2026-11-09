<?php

namespace App\Http\Requests\Api\V1\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $availableUser = fn () => Rule::exists('users', 'id')->whereNull('deleted_at');

        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'required', Rule::in(['pending', 'in_progress', 'completed'])],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'owner_id' => ['required', 'integer', $availableUser()],
            'assigned_user_id' => ['nullable', 'integer', $availableUser()],
        ];
    }
}
