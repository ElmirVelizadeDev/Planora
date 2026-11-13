<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(User::ROLES)],
            'status' => ['nullable', Rule::in(User::STATUSES)],
            'verification' => ['nullable', Rule::in(['verified', 'unverified'])],
            'deleted' => ['nullable', 'boolean'],
            'registered_from' => ['nullable', 'date'],
            'registered_to' => ['nullable', 'date', 'after_or_equal:registered_from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:10'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
