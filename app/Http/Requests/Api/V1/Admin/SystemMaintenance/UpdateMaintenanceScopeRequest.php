<?php

namespace App\Http\Requests\Api\V1\Admin\SystemMaintenance;

use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('enabled')) {
            $this->merge(['enabled' => $this->boolean('enabled')]);
        }
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'message' => [Rule::requiredIf(fn () => $this->boolean('enabled')), 'nullable', 'string', 'min:1', 'max:500'],
            'estimated_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'retry_after' => ['sometimes', 'required', 'integer', 'between:15,86400'],
        ];
    }

    public function scopeKey(): string
    {
        $scope = str_replace('-', '_', (string) $this->route('scope'));
        abort_unless(in_array($scope, MaintenanceScopeStore::SCOPES, true), 404);

        return $scope;
    }
}
