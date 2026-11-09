<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\User;
use App\Rules\PersonName;
use App\Rules\SupportedEmailDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        $routeUser = $this->route('user');
        $userId = $routeUser instanceof User ? $routeUser->id : $routeUser;
        $existingEmail = $routeUser instanceof User ? mb_strtolower($routeUser->email) : null;
        $nameRules = ['sometimes', 'required', 'string', 'max:255'];
        if ($this->has('name') && (! $routeUser instanceof User || trim((string) $this->input('name')) !== $routeUser->name)) {
            $nameRules[] = new PersonName();
            $nameRules = array_values(array_filter($nameRules, fn ($rule) => $rule !== 'max:255'));
        }
        $firstNameRules = ['sometimes', 'nullable', 'string', 'max:255'];
        if ($this->has('first_name') && (! $routeUser instanceof User || trim((string) $this->input('first_name')) !== (string) $routeUser->first_name)) {
            $firstNameRules = ['sometimes', 'nullable', 'string', new PersonName()];
        }
        $lastNameRules = ['sometimes', 'nullable', 'string', 'max:255'];
        if ($this->has('last_name') && (! $routeUser instanceof User || trim((string) $this->input('last_name')) !== (string) $routeUser->last_name)) {
            $lastNameRules = ['sometimes', 'nullable', 'string', new PersonName()];
        }
        $emailRules = ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)];
        if ($this->has('email') && mb_strtolower(trim((string) $this->input('email'))) !== $existingEmail) {
            $emailRules[] = new SupportedEmailDomain();
        }

        return [
            'name' => $nameRules,
            'first_name' => $firstNameRules,
            'last_name' => $lastNameRules,
            'email' => $emailRules,
            'username' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9().\s-]{7,32}$/'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4096,max_height=4096', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if (is_string($this->input('name'))) $data['name'] = preg_replace('/\s+/u', ' ', trim($this->input('name')));
        if (is_string($this->input('first_name'))) $data['first_name'] = preg_replace('/\s+/u', ' ', trim($this->input('first_name')));
        if (is_string($this->input('last_name'))) $data['last_name'] = preg_replace('/\s+/u', ' ', trim($this->input('last_name')));
        if (is_string($this->input('email'))) $data['email'] = mb_strtolower(trim($this->input('email')));
        $this->merge($data);
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($this->hasFile('avatar') && $this->boolean('remove_avatar')) {
                $validator->errors()->add('avatar', 'Choose either a replacement avatar or remove the current avatar.');
            }
        });
    }
}
