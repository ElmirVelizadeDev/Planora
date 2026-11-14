<?php

namespace App\Http\Requests\Api\V1\Admin\Reports;

use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' || $this->user()?->hasManagementPermission('reports.view');
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'task_status' => ['nullable', Rule::in(array_column(TaskStatus::cases(), 'value'))],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50])],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sort' => ['sometimes', 'nullable', Rule::in(['name', 'role', 'status', 'created_at', 'title', 'priority', 'due_date', 'projects', 'tasks'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $to = $this->input('to') ? CarbonImmutable::parse($this->input('to')) : CarbonImmutable::today();
            $from = $this->input('from') ? CarbonImmutable::parse($this->input('from')) : $to->subDays(29);
            if ($to->lessThan($from)) {
                $validator->errors()->add('to', 'The end date must be on or after the start date.');
                return;
            }

            if ($from->diffInDays($to) > 365) {
                $validator->errors()->add('to', 'The reporting range cannot exceed 366 days.');
                return;
            }

            $table = $this->route('table');
            $allowedSorts = [
                'registrations' => ['name', 'role', 'status', 'created_at'],
                'projects' => ['name', 'status', 'created_at'],
                'tasks' => ['title', 'status', 'priority', 'due_date', 'created_at'],
                'overdue' => ['title', 'status', 'priority', 'due_date', 'created_at'],
                'upcoming' => ['title', 'status', 'priority', 'due_date', 'created_at'],
                'categories' => ['name', 'status', 'projects', 'tasks'],
                'unused_categories' => ['name', 'status', 'projects', 'tasks'],
                'assignees' => ['name', 'role', 'status', 'tasks'],
            ];
            $sort = $this->input('sort');
            if ($table && $sort && ! in_array($sort, $allowedSorts[$table] ?? [], true)) {
                $validator->errors()->add('sort', 'The requested sort is not supported for this report table.');
            }
        });
    }
}
