<?php

namespace App\Rules;

use App\Support\MexicanId;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RFC de persona física (13 posiciones) o moral (12): estructura, fecha válida, dígito verificador y,
 * en personas físicas, coincidencia con el nacimiento y con el nombre capturados.
 */
class Rfc implements DataAwareRule, ValidationRule
{
    private const PATTERN = '/^([A-ZÑ&]{3,4})(\d{2})(\d{2})(\d{2})[A-Z0-9]{3}$/u';

    private const GENERIC = ['XAXX010101000', 'XEXX010101000'];

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

        // RFC genéricos del SAT (público en general y extranjeros): no siguen el algoritmo
        if (in_array($rfc, self::GENERIC, true)) {
            return;
        }

        if (MexicanId::rfcCheckDigit(mb_substr($rfc, 0, -1)) !== mb_substr($rfc, -1)) {
            $fail('El RFC no es válido: revisa que esté completo y bien escrito, incluida la homoclave.');

            return;
        }

        $isPerson = mb_strlen($letters) === 4;
        $birthday = $this->birthday();
        if ($isPerson && $birthday && $birthday->format('ymd') !== $yy.$mm.$dd) {
            $fail('El RFC no coincide con la fecha de nacimiento capturada.');

            return;
        }

        $paternal = $this->data['last_name'] ?? null;
        $names = trim(($this->data['name'] ?? '').' '.($this->data['second_name'] ?? ''));
        if ($isPerson && filled($paternal) && $names !== '') {
            $expected = MexicanId::rfcInitials($paternal, $this->data['maiden_name'] ?? null, $names);
            // El SAT admite Ñ o X en las iniciales
            if (str_replace('Ñ', 'X', $letters) !== str_replace('Ñ', 'X', $expected)) {
                $fail('El RFC no coincide con el nombre y los apellidos capturados.');
            }
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
