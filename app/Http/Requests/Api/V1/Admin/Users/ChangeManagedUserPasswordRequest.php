<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use App\Models\User;
use App\Rules\ConfiguredPasswordPolicy;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class ChangeManagedUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changePassword', $this->route('user')) ?? false;
    }

    public function rules(SettingsService $settings): array
    {
        $minimum = max(12, (int) ($settings->passwordPolicy()['minimum_password_length'] ?? 12));
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min($minimum)->mixedCase()->numbers(), new ConfiguredPasswordPolicy()],
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
