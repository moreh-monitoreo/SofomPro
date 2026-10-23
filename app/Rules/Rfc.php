<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/** RFC de persona física (13 posiciones) o moral (12): estructura, fecha válida y coincidencia con el nacimiento. */
class Rfc implements DataAwareRule, ValidationRule
{
    private const PATTERN = '/^([A-ZÑ&]{3,4})(\d{2})(\d{2})(\d{2})[A-Z0-9]{3}$/u';

    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rfc = mb_strtoupper(trim((string) $value));

        if (! preg_match(self::PATTERN, $rfc, $m)) {
            $fail('El RFC no tiene un formato válido.');

            return;
        }

        [, $letters, $yy, $mm, $dd] = $m;
        if (! Curp::isRealDate((int) $yy, (int) $mm, (int) $dd)) {
            $fail('La fecha dentro del RFC no es válida.');

            return;
        }

        $isPerson = mb_strlen($letters) === 4;
        $birthday = $this->birthday();
        if ($isPerson && $birthday && $birthday->format('ymd') !== $yy.$mm.$dd) {
            $fail('El RFC no coincide con la fecha de nacimiento capturada.');
        }
    }

    private function birthday(): ?Carbon
    {
        try {
            return isset($this->data['birthday']) ? Carbon::parse($this->data['birthday']) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
