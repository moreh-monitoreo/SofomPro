<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** CLABE interbancaria: 18 dígitos y dígito de control (pesos 3, 7, 1 sobre los primeros 17). */
class Clabe implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clabe = preg_replace('/\s+/', '', (string) $value);

        if (! preg_match('/^\d{18}$/', $clabe)) {
            $fail('La CLABE debe tener 18 dígitos.');

            return;
        }

        if (self::checkDigit(substr($clabe, 0, 17)) !== (int) $clabe[17]) {
            $fail('La CLABE no es válida; revisa que esté bien escrita.');
        }
    }

    public static function checkDigit(string $first17): int
    {
        $weights = [3, 7, 1];
        $sum = 0;
        foreach (str_split($first17) as $i => $digit) {
            $sum += ((int) $digit * $weights[$i % 3]) % 10;
        }

        return (10 - $sum % 10) % 10;
    }
}
