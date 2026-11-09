<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordRecoveryOtpRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['email' => ['required', 'email:rfc', 'max:255'], 'code' => ['required', 'regex:/^\d{6}$/']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email]);
    }
}
