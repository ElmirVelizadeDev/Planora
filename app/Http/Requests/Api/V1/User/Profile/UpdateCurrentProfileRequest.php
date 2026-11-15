<?php

namespace App\Http\Requests\Api\V1\User\Profile;

use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCurrentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', new PersonName()],
            'last_name' => ['sometimes', 'nullable', 'string', new PersonName()],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9().\s-]{7,32}$/'],
            'name' => ['prohibited'],
            'email' => ['prohibited'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'moderator_permissions' => ['prohibited'],
            'password' => ['prohibited'],
            'profile_photo' => ['prohibited'],
            'avatar' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['first_name', 'last_name'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = preg_replace('/\s+/u', ' ', trim($this->input($field)));
            }
        }
        if (is_string($this->input('phone'))) {
            $normalized['phone'] = trim($this->input('phone'));
        }
        $this->merge($normalized);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $parts = preg_split('/\s+/', trim((string) ($user?->name ?? '')), 2) ?: [];
            $first = (string) $this->input('first_name', $user?->first_name ?: ($parts[0] ?? ''));
            $last = (string) $this->input('last_name', $user?->last_name ?: ($parts[1] ?? ''));
            if (mb_strlen(trim($first.' '.$last)) > 50) {
                $validator->errors()->add('first_name', 'The full name must be 50 characters or fewer.');
            }
        });
    }
}
