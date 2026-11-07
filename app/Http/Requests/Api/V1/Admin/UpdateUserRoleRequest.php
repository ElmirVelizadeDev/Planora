<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is protected by auth:sanctum and role:admin middleware.
        // FormRequest's default guard lookup can differ from Sanctum's resolved user.
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['user', 'admin'])],
        ];
    }
}
