<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use App\Models\User;
use App\Rules\PersonName;
use App\Rules\SupportedEmailDomain;
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
            'name' => ['required', 'string', new PersonName()],
            'first_name' => ['sometimes', 'nullable', 'string', new PersonName()],
            'last_name' => ['sometimes', 'nullable', 'string', new PersonName()],
            'email' => ['required', 'email:rfc', 'max:255', new SupportedEmailDomain(), 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9().\s-]{7,32}$/'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4096,max_height=4096', 'max:2048'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role' => ['required', Rule::in(User::ROLES)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'moderator_permissions' => ['nullable', 'array'],
            'moderator_permissions.*' => ['string', Rule::in(array_keys(config('user_management.moderator_permissions')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? preg_replace('/\s+/u', ' ', trim($this->name)) : $this->name,
            'first_name' => is_string($this->first_name) ? preg_replace('/\s+/u', ' ', trim($this->first_name)) : $this->first_name,
            'last_name' => is_string($this->last_name) ? preg_replace('/\s+/u', ' ', trim($this->last_name)) : $this->last_name,
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (mb_strlen(trim(($this->input('first_name', '')).' '.($this->input('last_name', '')))) > 50) {
                $validator->errors()->add('name', 'The full name must be 50 characters or fewer.');
            }
            $permissions = $this->input('moderator_permissions', []);
            if (! is_array($permissions)) return;
            if (in_array('users.status.manage', $permissions, true) && ! in_array('users.view', $permissions, true)) {
                $validator->errors()->add('moderator_permissions', 'Account status management also requires user visibility permission.');
            }
        });
    }
}
