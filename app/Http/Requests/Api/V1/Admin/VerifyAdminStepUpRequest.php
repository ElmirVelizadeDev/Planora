<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAdminStepUpRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', 'in:roles.manage,users.manage,backups.delete,backups.restore,settings.security,mfa.disable,mfa.recovery,system.maintenance'],
            'password' => ['nullable', 'string', 'max:128'],
            'code' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'recovery_code' => ['nullable', 'string', 'alpha_num', 'size:10'],
        ];
    }
}
