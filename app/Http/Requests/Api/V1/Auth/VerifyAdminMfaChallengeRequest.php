<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAdminMfaChallengeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'regex:/^\d{6}$/', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'alpha_num', 'size:10', 'required_without:code'],
        ];
    }
}
