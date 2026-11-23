<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\PasswordPolicy;
use App\Rules\PersonName;
use App\Rules\SupportedEmailDomain;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(SettingsService $settings): array
    {
        return [
            'name' => ['required', 'string', new PersonName()],
            // Duplicate-account handling lives in RegistrationService so the API response
            // does not disclose whether an address is already registered.
            'email' => ['required', 'string', 'email:rfc', 'max:255', new SupportedEmailDomain()],
            'password' => [...PasswordPolicy::rules($settings), 'confirmed'],
            'terms_accepted' => ['required', 'accepted'],
            'role' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? preg_replace('/\s+/u', ' ', trim($this->name)) : $this->name,
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }
}
