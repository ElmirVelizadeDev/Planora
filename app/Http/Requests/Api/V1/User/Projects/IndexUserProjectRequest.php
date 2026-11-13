<?php

namespace App\Http\Requests\Api\V1\User\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserProjectRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'user'; }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['planned', 'active', 'on_hold', 'completed'])],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
