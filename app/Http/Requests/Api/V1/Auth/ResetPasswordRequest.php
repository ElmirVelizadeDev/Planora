<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\PasswordPolicy;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(SettingsService $settings): array
    {
        return [
            'password' => [...PasswordPolicy::rules($settings), 'confirmed'],
        ];
    }
}
