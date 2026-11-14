<?php

namespace App\Http\Requests\Api\V1\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminReportFilterOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' || $this->user()?->hasManagementPermission('reports.view');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['users', 'projects', 'categories'])],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }
}
