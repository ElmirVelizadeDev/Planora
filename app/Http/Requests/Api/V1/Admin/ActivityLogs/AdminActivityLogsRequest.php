<?php

namespace App\Http\Requests\Api\V1\Admin\ActivityLogs;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminActivityLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewActivity', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'actor_search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'actor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'module' => ['sometimes', 'nullable', Rule::in(['Authentication', 'Users', 'Categories', 'Projects', 'Tasks', 'Reports', 'Settings', 'System'])],
            'subject_type' => ['sometimes', 'nullable', Rule::in(['User', 'Category', 'Project', 'Task'])],
            'event' => ['sometimes', 'nullable', 'string', 'max:80', Rule::exists('user_activity_logs', 'event')],
            'status' => ['sometimes', 'nullable', Rule::in(['success', 'failure'])],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50, 100])],
            'sort' => ['sometimes', Rule::in(['created_at', 'event', 'module', 'status', 'actor'])],
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
            } elseif ($from->diffInDays($to) > 365) {
                $validator->errors()->add('to', 'The reporting range cannot exceed 366 days.');
            }
        });
    }
}
