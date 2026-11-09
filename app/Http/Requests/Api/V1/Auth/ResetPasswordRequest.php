<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Rules\NotCommonRegistrationPassword;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'max:128', new NotCommonRegistrationPassword(), 'confirmed'],
        ];
    }
}
