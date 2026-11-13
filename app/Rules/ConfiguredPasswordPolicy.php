<?php

namespace App\Rules;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ConfiguredPasswordPolicy implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) return;

        $policy = app(SettingsService::class)->passwordPolicy();
        $checks = [
            'require_uppercase' => ['/[A-Z]/', 'uppercase letter'],
            'require_lowercase' => ['/[a-z]/', 'lowercase letter'],
            'require_number' => ['/[0-9]/', 'number'],
            'require_special_character' => ['/[^a-zA-Z0-9]/', 'special character'],
        ];
        foreach ($checks as $key => [$pattern, $label]) {
            if (($policy[$key] ?? false) && ! preg_match($pattern, $value)) {
                $fail("The password must include at least one {$label}.");
            }
        }
    }
}
