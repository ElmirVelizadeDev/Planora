<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChangeUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(User::ROLES)],
            'moderator_permissions' => ['nullable', 'array'],
            'moderator_permissions.*' => ['string', Rule::in(array_keys(config('user_management.moderator_permissions')))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $permissions = $this->input('moderator_permissions', []);
            if (! is_array($permissions)) return;
            if (in_array('users.status.manage', $permissions, true) && ! in_array('users.view', $permissions, true)) {
                $validator->errors()->add('moderator_permissions', 'Account status management also requires user visibility permission.');
            }
        });
    }
}
