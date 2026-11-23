<?php

namespace App\Rules;

use App\Support\MexicanId;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CURP de 18 posiciones: estructura oficial, fecha de nacimiento válida, dígito verificador y, si vienen,
 * coincidencia con la fecha de nacimiento, el género, el nombre y los apellidos capturados.
 */
class Curp implements DataAwareRule, ValidationRule
{
    private const PATTERN = '/^[A-Z]{4}(\d{2})(\d{2})(\d{2})([HMX])(AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9]\d$/';

    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $curp = strtoupper(trim((string) $value));

        if (! preg_match(self::PATTERN, $curp, $m)) {
            $fail('La CURP no tiene un formato válido.');

            return;
        }

        [, $yy, $mm, $dd, $sex] = $m;
        if (! self::isRealDate((int) $yy, (int) $mm, (int) $dd)) {
            $fail('La fecha de nacimiento dentro de la CURP no es válida.');

            return;
        }

        $birthday = $this->birthday();
        if ($birthday && $birthday->format('ymd') !== $yy.$mm.$dd) {
            $fail('La CURP no coincide con la fecha de nacimiento capturada.');

            return;
        }

        $gender = $this->data['gender'] ?? null;
        $expected = match ($gender) {
            'Femenino' => 'M',
            'Masculino' => 'H',
            default => null,
        };
        if ($expected && $sex !== 'X' && $sex !== $expected) {
            $fail('La CURP no coincide con el género capturado.');

            return;
        }

        if (MexicanId::curpCheckDigit(substr($curp, 0, 17)) !== substr($curp, 17)) {
            $fail('La CURP no es válida: revisa que esté completa y bien escrita.');

            return;
        }

        $paternal = $this->data['last_name'] ?? null;
        $names = trim(($this->data['name'] ?? '').' '.($this->data['second_name'] ?? ''));
        if (filled($paternal) && $names !== '') {
            $maternal = $this->data['maiden_name'] ?? null;
            $expectedLetters = MexicanId::curpInitials($paternal, $maternal, $names).MexicanId::curpConsonants($paternal, $maternal, $names);
            if (substr($curp, 0, 4).substr($curp, 13, 3) !== $expectedLetters) {
                $fail('La CURP no coincide con el nombre y los apellidos capturados. Escríbelos como aparecen en tu acta de nacimiento.');
            }
        }
    }

    public static function isRealDate(int $yy, int $mm, int $dd): bool
    {
        foreach ([1900 + $yy, 2000 + $yy] as $year) {
            if (checkdate($mm, $dd, $year)) {
                return true;
            }
        }

        return false;
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
