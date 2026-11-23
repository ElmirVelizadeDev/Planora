<?php

namespace App\Support;

use App\Rules\ConfiguredPasswordPolicy;
use App\Rules\NotCommonRegistrationPassword;
use App\Services\Settings\SettingsService;
use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    /** One creation/change policy; breach checks use Laravel's k-anonymity range API. */
    public static function rules(?SettingsService $settings = null): array
    {
        $minimum = max(12, (int) (($settings ?? app(SettingsService::class))->passwordPolicy()['minimum_password_length'] ?? 12));
        $password = Password::min($minimum)->max(128);
        if (config('auth.password_policy.check_breaches', true)) $password->uncompromised();

        return ['required', 'string', $password, new NotCommonRegistrationPassword(), new ConfiguredPasswordPolicy()];
    }
}
