<?php

namespace App\Http\Requests\Api\V1\User\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'user';
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])]];
    }
}
