<?php

namespace App\Http\Requests\Api\V1\Admin\SystemMaintenance;

use Illuminate\Foundation\Http\FormRequest;

class EnableMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'retry_after' => $this->input('retry_after', config('planora.maintenance.retry_after', 60)),
        ]);
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:500'],
            'estimated_at' => ['nullable', 'date', 'after:now'],
            'retry_after' => ['required', 'integer', 'between:15,86400'],
        ];
    }
}
