<?php

namespace App\Http\Requests\Api\V1\User\Profile;

use App\Rules\ConfiguredPasswordPolicy;
use App\Rules\NotCommonRegistrationPassword;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class ChangeCurrentPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(SettingsService $settings): array
    {
        $minimum = max(12, (int) ($settings->passwordPolicy()['minimum_password_length'] ?? 12));

        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min($minimum)->mixedCase()->numbers(), new NotCommonRegistrationPassword(), new ConfiguredPasswordPolicy()],
            'email' => ['prohibited'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'moderator_permissions' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $current = (string) $this->input('current_password', '');
            $next = (string) $this->input('password', '');

            if ($user && $current !== '' && ! Hash::check($current, $user->password)) {
                $validator->errors()->add('current_password', 'The current password is incorrect.');
            }
            if ($user && $next !== '' && Hash::check($next, $user->password)) {
                $validator->errors()->add('password', 'Choose a password different from your current password.');
            }
        });
    }
}
