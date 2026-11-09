<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) < 2 || mb_strlen($value) > 50
            || ! preg_match("/^[\\p{L}]+(?:[ '-][\\p{L}]+)*$/u", $value)) {
            $fail('Enter 2–50 characters using letters, spaces, hyphens, or apostrophes.');
        }
    }
}
