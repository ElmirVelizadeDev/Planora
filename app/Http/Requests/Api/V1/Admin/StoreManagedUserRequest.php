<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9().\s-]{7,32}$/'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4096,max_height=4096', 'max:2048'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role' => ['required', Rule::in(User::ROLES)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
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
