<?php

namespace App\Http\Requests\Api\V1\User\Reports;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserTaskReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'user';
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('start_date') || ! $this->filled('end_date')) {
                return;
            }

            $utc = new DateTimeZone('UTC');
            $start = new DateTimeImmutable($this->input('start_date'), $utc);
            $end = new DateTimeImmutable($this->input('end_date'), $utc);
            if ((int) $start->diff($end)->days + 1 > 180) {
                $validator->errors()->add('end_date', 'The report range may not exceed 180 included dates.');
            }
        });
    }
}
