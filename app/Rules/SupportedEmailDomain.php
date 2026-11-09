<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SupportedEmailDomain implements ValidationRule
{
    public const DOMAINS = [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com',
        'yahoo.com', 'yahoo.co.uk', 'icloud.com', 'me.com', 'mac.com',
        'proton.me', 'protonmail.com', 'gmx.com', 'gmx.de', 'zoho.com',
        'yandex.com', 'yandex.ru',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = is_string($value) ? mb_strtolower((string) strrchr($value, '@')) : '';
        $domain = ltrim($domain, '@');

        if (! in_array($domain, self::DOMAINS, true)) {
            $fail('Use an email address from a supported provider: Gmail, Outlook, Hotmail, Live, Yahoo, iCloud, Proton, GMX, Zoho, or Yandex.');
        }
    }
}
