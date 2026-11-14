<?php

namespace App\Http\Requests\Api\V1\Admin\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageStatus', $this->route('project')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['planned', 'active', 'on_hold', 'completed'])],
        ];
    }
}
