<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotCommonRegistrationPassword implements ValidationRule
{
    private const COMMON = [
        'password', 'password1', 'password123', 'passw0rd', '12345678', '123456789',
        'qwerty', 'qwerty123', 'letmein', 'welcome', 'welcome1', 'admin', 'admin123',
        'iloveyou', 'monkey', 'dragon', 'sunshine', 'football', 'baseball', 'master',
        'login', 'abc12345', '00000000', '11111111',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isCommonOrPredictable($value)) {
            $fail('Choose a less predictable password.');
        }
    }

    public static function isCommonOrPredictable(string $password): bool
    {
        $normalized = mb_strtolower(trim($password));
        $compact = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        if (in_array($normalized, self::COMMON, true) || in_array($compact, self::COMMON, true)) {
            return true;
        }

        if (preg_match('/^(password|qwerty|letmein|welcome|admin|iloveyou|monkey|dragon|sunshine|football|baseball|master|login|passw0rd)[\W_]*\d*$/iu', $compact)) {
            return true;
        }

        if (preg_match('/^(.)\1{7,}$/us', $password) || preg_match('/^(.{1,4})\1{2,}$/us', $password)) {
            return true;
        }

        foreach (['012345678', '123456789', '987654321', 'abcdefgh', 'hgfedcba', 'qwertyui', 'asdfghjk', 'zxcvbnm'] as $sequence) {
            if (str_contains($compact, $sequence)) {
                return true;
            }
        }

        return false;
    }
}
