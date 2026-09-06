<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a NIS Service Number: digits only, configurable length.
 * Rejects letters, "/", "-", spaces and other characters, and preserves
 * leading zeroes (validation is string-based, never cast to int).
 */
class ServiceNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[0-9]+$/', $value)) {
            $fail('The Service Number must contain digits only.');

            return;
        }

        $length = config('personnel.service_number.length');
        if ($length !== null) {
            if (strlen($value) !== (int) $length) {
                $fail("The Service Number must be exactly {$length} digits.");
            }

            return;
        }

        $min = (int) config('personnel.service_number.min', 4);
        $max = (int) config('personnel.service_number.max', 12);
        $len = strlen($value);

        if ($len < $min || $len > $max) {
            $fail("The Service Number must be between {$min} and {$max} digits.");
        }
    }
}
