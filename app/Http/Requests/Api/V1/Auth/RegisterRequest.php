<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Rules\NotCommonRegistrationPassword;
use App\Rules\PersonName;
use App\Rules\SupportedEmailDomain;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', new PersonName()],
            // Duplicate-account handling lives in RegistrationService so the API response
            // does not disclose whether an address is already registered.
            'email' => ['required', 'string', 'email:rfc', 'max:255', new SupportedEmailDomain()],
            'password' => ['required', 'string', 'min:8', 'max:128', new NotCommonRegistrationPassword(), 'confirmed'],
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
