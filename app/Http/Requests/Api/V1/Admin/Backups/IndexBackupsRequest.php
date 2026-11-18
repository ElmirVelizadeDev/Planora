<?php

namespace App\Http\Requests\Api\V1\Admin\Backups;

use Illuminate\Foundation\Http\FormRequest;

class IndexBackupsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:80'],
            'type' => ['sometimes', 'in:database,files,full'],
            'status' => ['sometimes', 'in:running,successful,failed'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
