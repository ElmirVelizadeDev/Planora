<?php

namespace App\Http\Requests\Api\V1\User\Profile;

use App\Support\PasswordPolicy;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class ChangeCurrentPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(SettingsService $settings): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [...PasswordPolicy::rules($settings), 'confirmed'],
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
