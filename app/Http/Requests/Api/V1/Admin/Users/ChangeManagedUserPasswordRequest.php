<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use App\Models\User;
use App\Support\PasswordPolicy;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class ChangeManagedUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changePassword', $this->route('user')) ?? false;
    }

    public function rules(SettingsService $settings): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [...PasswordPolicy::rules($settings), 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            $currentPassword = (string) $this->input('current_password', '');

            if ($user && $currentPassword !== '' && ! Hash::check($currentPassword, $user->password)) {
                $validator->errors()->add('current_password', 'The current password is incorrect.');
            }

            if ($user && $this->filled('password') && Hash::check((string) $this->input('password'), $user->password)) {
                $validator->errors()->add('password', 'Choose a password different from your current password.');
            }
        });
    }
}
