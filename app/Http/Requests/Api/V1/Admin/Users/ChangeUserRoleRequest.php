<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Http\Requests\Api\V1\Admin\Users\Concerns\ValidatesModeratorPermissionDependencies;

class ChangeUserRoleRequest extends FormRequest
{
    use ValidatesModeratorPermissionDependencies;

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(User::ROLES)],
            'moderator_permissions' => ['nullable', 'array'],
            'moderator_permissions.*' => ['string', 'distinct', Rule::in(array_keys(config('user_management.moderator_permissions')))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $permissions = $this->input('moderator_permissions', []);
            if ($this->input('role') !== 'moderator' && is_array($permissions) && $permissions !== []) {
                $validator->errors()->add('moderator_permissions', 'Permissions can only be assigned when the account role is Moderator.');
            }
            $this->validateModeratorPermissionDependencies($validator, 'moderator_permissions');
        });
    }
}
