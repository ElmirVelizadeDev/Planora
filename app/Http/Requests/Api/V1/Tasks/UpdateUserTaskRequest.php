<?php

namespace App\Http\Requests\Api\V1\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }
}
