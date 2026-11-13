<?php

namespace App\Http\Requests\Api\V1\Admin\Settings;

use App\Services\Settings\SettingsRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(SettingsRegistry $registry): array
    {
        $group = (string) $this->route('group');
        $rules = $registry->validationRules($group);
        if ($group === 'email') {
            unset($rules['mail_password']);
            $rules['mail_password'] = ['sometimes', 'nullable', 'string', 'max:1000'];
            $rules['remove_mail_password'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
