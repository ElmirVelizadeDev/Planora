<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Rules\NotCommonRegistrationPassword;
use App\Rules\ConfiguredPasswordPolicy;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(SettingsService $settings): array
    {
        $minimum = max(8, (int) ($settings->passwordPolicy()['minimum_password_length'] ?? 8));
        return [
            'password' => ['required', 'string', 'min:'.$minimum, 'max:128', new NotCommonRegistrationPassword(), new ConfiguredPasswordPolicy(), 'confirmed'],
        ];
    }
}
