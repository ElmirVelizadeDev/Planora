<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Http\Requests\Api\V1\Admin\Users\Concerns\ValidatesModeratorPermissionDependencies;

class UpdateModeratorPermissionsRequest extends FormRequest
{
    use ValidatesModeratorPermissionDependencies;

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
            $this->validateModeratorPermissionDependencies($validator, 'permissions');
        });
    }
}
