<?php

namespace App\Http\Requests\Api\V1\Admin\Backups;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBackupActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['confirmation' => ['required', 'string', 'max:80']];
    }
}
