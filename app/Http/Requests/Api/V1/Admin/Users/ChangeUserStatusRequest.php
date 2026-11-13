<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeStatus', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(User::STATUSES)],
            'reason' => ['required_if:status,suspended,banned', 'nullable', 'string', 'min:5', 'max:2000'],
        ];
    }
}
