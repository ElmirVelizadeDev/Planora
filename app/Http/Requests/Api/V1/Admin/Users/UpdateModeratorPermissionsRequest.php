<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateModeratorPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changePermissions', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(array_keys(config('user_management.moderator_permissions')))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $permissions = $this->input('permissions', []);
            if (! is_array($permissions)) return;
            if (in_array('users.status.manage', $permissions, true) && ! in_array('users.view', $permissions, true)) {
                $validator->errors()->add('permissions', 'Account status management also requires user visibility permission.');
            }
        });
    }
}
