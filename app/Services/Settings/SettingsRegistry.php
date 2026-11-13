<?php

namespace App\Services\Settings;

class SettingsRegistry
{
    private static ?array $environmentDefaults = null;

    public function groups(): array
    {
        self::$environmentDefaults ??= [
            'app_name' => config('app.name', 'Planora'),
            'timezone' => config('app.timezone', 'UTC'),
            'mailer' => config('mail.default', 'smtp'),
            'mail_host' => config('mail.mailers.smtp.host', ''),
            'mail_port' => (int) config('mail.mailers.smtp.port', 587),
            'mail_encryption' => config('mail.mailers.smtp.encryption', 'tls') ?: 'none',
            'mail_username' => config('mail.mailers.smtp.username', '') ?: '',
            'mail_from_address' => config('mail.from.address', 'hello@example.com'),
            'mail_from_name' => config('mail.from.name', 'Planora'),
        ];
        $defaults = self::$environmentDefaults;
        return [
            'general' => [
                'application_name' => ['type' => 'string', 'default' => $defaults['app_name'], 'rules' => ['required', 'string', 'max:100']],
                'workspace_name' => ['type' => 'string', 'default' => 'Planora Workspace', 'rules' => ['required', 'string', 'max:100']],
                'workspace_description' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:500']],
                'contact_email' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'email:rfc', 'max:255']],
                'default_language' => ['type' => 'string', 'default' => 'en', 'rules' => ['required', 'in:en']],
                'timezone' => ['type' => 'string', 'default' => $defaults['timezone'], 'rules' => ['required', 'timezone']],
                'date_format' => ['type' => 'string', 'default' => 'Y-m-d', 'rules' => ['required', 'in:Y-m-d,d/m/Y,m/d/Y,d M Y']],
                'time_format' => ['type' => 'string', 'default' => '24h', 'rules' => ['required', 'in:24h,12h']],
                'application_logo' => ['type' => 'string', 'default' => null, 'rules' => ['prohibited']],
            ],
            'security' => [
                'minimum_password_length' => ['type' => 'integer', 'default' => 8, 'rules' => ['required', 'integer', 'min:8', 'max:64']],
                'require_uppercase' => ['type' => 'boolean', 'default' => false, 'rules' => ['required', 'boolean']],
                'require_lowercase' => ['type' => 'boolean', 'default' => false, 'rules' => ['required', 'boolean']],
                'require_number' => ['type' => 'boolean', 'default' => false, 'rules' => ['required', 'boolean']],
                'require_special_character' => ['type' => 'boolean', 'default' => false, 'rules' => ['required', 'boolean']],
                'max_login_attempts' => ['type' => 'integer', 'default' => 10, 'rules' => ['required', 'integer', 'min:3', 'max:20']],
                'login_lockout_minutes' => ['type' => 'integer', 'default' => 1, 'rules' => ['required', 'integer', 'min:1', 'max:60']],
                'otp_expiration_minutes' => ['type' => 'integer', 'default' => 5, 'rules' => ['required', 'integer', 'min:2', 'max:15']],
                'otp_max_attempts' => ['type' => 'integer', 'default' => 5, 'rules' => ['required', 'integer', 'min:3', 'max:10']],
                'otp_resend_cooldown_seconds' => ['type' => 'integer', 'default' => 60, 'rules' => ['required', 'integer', 'min:30', 'max:600']],
            ],
            'email' => [
                'mail_mailer' => ['type' => 'string', 'default' => $defaults['mailer'], 'rules' => ['required', 'in:smtp,sendmail,log,array']],
                'mail_host' => ['type' => 'string', 'default' => $defaults['mail_host'], 'rules' => ['nullable', 'string', 'max:255', 'regex:/^(?=.{1,253}$)(?:localhost|(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\.(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))*)$/']],
                'mail_port' => ['type' => 'integer', 'default' => $defaults['mail_port'], 'rules' => ['required', 'integer', 'min:1', 'max:65535']],
                'mail_encryption' => ['type' => 'string', 'default' => $defaults['mail_encryption'], 'rules' => ['required', 'in:none,ssl,tls']],
                'mail_username' => ['type' => 'string', 'default' => $defaults['mail_username'], 'rules' => ['nullable', 'string', 'max:255']],
                'mail_password' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:1000'], 'sensitive' => true],
                'mail_from_address' => ['type' => 'string', 'default' => $defaults['mail_from_address'], 'rules' => ['required', 'email:rfc', 'max:255']],
                'mail_from_name' => ['type' => 'string', 'default' => $defaults['mail_from_name'], 'rules' => ['required', 'string', 'max:100']],
            ],
        ];
    }

    public function group(string $group): array
    {
        return $this->groups()[$group] ?? [];
    }

    public function validationRules(string $group): array
    {
        $rules = [];
        foreach ($this->group($group) as $key => $definition) {
            $rules[$key] = $definition['rules'];
        }

        return $rules;
    }
}
