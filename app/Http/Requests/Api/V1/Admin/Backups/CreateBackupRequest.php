<?php

namespace App\Http\Requests\Api\V1\Admin\Backups;

use Illuminate\Foundation\Http\FormRequest;

class CreateBackupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['type' => ['required', 'in:database,files,full']];
    }
}
