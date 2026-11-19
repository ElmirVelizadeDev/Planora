<?php

namespace App\Http\Requests\Api\V1\User\Tasks;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CalendarRangeRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('start_date') || ! $this->filled('end_date')) {
                return;
            }

            $utc = new DateTimeZone('UTC');
            $start = new DateTimeImmutable($this->input('start_date'), $utc);
            $end = new DateTimeImmutable($this->input('end_date'), $utc);
            $includedDays = (int) $start->diff($end)->days + 1;

            if ($includedDays > 180) {
                $validator->errors()->add('end_date', 'The calendar date range may not exceed 180 included dates.');
            }
        });
    }
}
