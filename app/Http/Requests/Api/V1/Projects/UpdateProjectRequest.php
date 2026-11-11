<?php

namespace App\Http\Requests\Api\V1\Projects;

use App\Models\Project;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends StoreProjectRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'status' => ['sometimes', 'required', Rule::in(['planned', 'active', 'on_hold', 'completed'])],
            'owner_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('role', 'user')->where('status', 'active'))],
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $project = $this->route('project');
            if (! $project instanceof Project) {
                return;
            }

            $startDate = $this->has('start_date') ? $this->input('start_date') : $project->start_date?->format('Y-m-d');
            $dueDate = $this->has('due_date') ? $this->input('due_date') : $project->due_date?->format('Y-m-d');

            if ($startDate && $dueDate && $dueDate < $startDate) {
                $validator->errors()->add('due_date', 'The due date must be after or equal to the start date.');
            }
        });
    }
}
