<?php

namespace App\Support;

/**
 * Lo que se puede calcular de una CURP o un RFC a partir del nombre: las letras iniciales,
 * las consonantes internas de la CURP y los dígitos verificadores.
 *
 * Reglas de RENAPO (Instructivo normativo para la asignación de la CURP) y del SAT (algoritmo
 * del RFC de personas físicas). La homoclave del RFC (posiciones 11 y 12) la asigna el SAT y no
 * se verifica; solo su dígito verificador final.
 */
class MexicanId
{
    /** Partículas que no cuentan en nombres ni apellidos: "de la Cruz" se toma como "Cruz". */
    private const PARTICLES = ['DA', 'DAS', 'DE', 'DEL', 'DER', 'DI', 'DIE', 'DD', 'EL', 'LA', 'LOS', 'LAS', 'LE', 'LES', 'MAC', 'MC', 'VAN', 'VON', 'Y', 'MI'];

    /** Primeros nombres que se saltan cuando hay otro nombre después (María Fernanda → F). */
    private const SKIPPED_NAMES = ['MARIA', 'MA', 'MA.', 'M', 'M.', 'JOSE', 'J', 'J.'];

    /** Palabras que RENAPO sustituye cambiando la segunda letra por X. */
    private const CURP_FORBIDDEN = [
        'BACA', 'BAKA', 'BUEI', 'BUEY', 'CACA', 'CACO', 'CAGA', 'CAGO', 'CAKA', 'CAKO', 'COGE', 'COGI', 'COJA', 'COJE', 'COJI', 'COJO',
        'COLA', 'CULO', 'FALO', 'FETO', 'GETA', 'GUEI', 'GUEY', 'JETA', 'JOTO', 'KACA', 'KACO', 'KAGA', 'KAGO', 'KAKA', 'KAKO', 'KOGE',
        'KOGI', 'KOJA', 'KOJE', 'KOJI', 'KOJO', 'KOLA', 'KULO', 'LILO', 'LOCA', 'LOCO', 'LOKA', 'LOKO', 'MAME', 'MAMO', 'MEAR', 'MEAS',
        'MEON', 'MIAR', 'MION', 'MOCO', 'MOKO', 'MULA', 'MULO', 'NACA', 'NACO', 'PEDA', 'PEDO', 'PENE', 'PIPI', 'PITO', 'POPO', 'PUTA',
        'PUTO', 'QULO', 'RATA', 'ROBA', 'ROBE', 'ROBO', 'RUIN', 'SENO', 'TETA', 'VACA', 'VAGA', 'VAGO', 'VAKA', 'VUEI', 'VUEY', 'WUEI', 'WUEY',
    ];

    /** Palabras que el SAT sustituye cambiando la última letra por X. */
    private const RFC_FORBIDDEN = [
        'BUEI', 'BUEY', 'CACA', 'CACO', 'CAGA', 'CAGO', 'CAKA', 'CAKO', 'COGE', 'COJA', 'COJE', 'COJI', 'COJO', 'CULO', 'FETO', 'GUEY',
        'JOTO', 'KACA', 'KACO', 'KAGA', 'KAGO', 'KOGE', 'KOJO', 'KAKA', 'KULO', 'MAME', 'MAMO', 'MEAR', 'MEAS', 'MEON', 'MION', 'MOCO',
        'MULA', 'PEDA', 'PEDO', 'PENE', 'PUTA', 'PUTO', 'QULO', 'RATA', 'RUIN',
    ];

    private const CURP_ALPHABET = '0123456789ABCDEFGHIJKLMNÑOPQRSTUVWXYZ';

    private const RFC_ALPHABET = '0123456789ABCDEFGHIJKLMN&OPQRSTUVWXYZ Ñ';

    /** Las 4 letras iniciales de la CURP. */
    public static function curpInitials(string $paternal, ?string $maternal, string $names): string
    {
        $paternal = self::surname($paternal);
        $maternal = self::surname((string) $maternal);
        $name = self::givenName($names);

        $initials = self::first($paternal).self::internalVowel($paternal).self::first($maternal).self::first($name);
        $initials = str_replace('Ñ', 'X', $initials);

        return in_array($initials, self::CURP_FORBIDDEN, true) ? $initials[0].'X'.substr($initials, 2) : $initials;
    }

    /** Las consonantes internas de la CURP (posiciones 14 a 16). */
    public static function curpConsonants(string $paternal, ?string $maternal, string $names): string
    {
        return str_replace('Ñ', 'X', self::internalConsonant(self::surname($paternal))
            .self::internalConsonant(self::surname((string) $maternal))
            .self::internalConsonant(self::givenName($names)));
    }

    /** Dígito verificador de la CURP (posición 18) a partir de las primeras 17. */
    public static function curpCheckDigit(string $first17): string
    {
        $sum = 0;
        foreach (mb_str_split(mb_strtoupper($first17)) as $i => $char) {
            $value = mb_strpos(self::CURP_ALPHABET, $char);
            $sum += ($value === false ? 0 : $value) * (18 - $i);
        }

        return (string) ((10 - $sum % 10) % 10);
    }

    /** Las 4 letras iniciales del RFC de persona física. */
    public static function rfcInitials(string $paternal, ?string $maternal, string $names): string
    {
        $paternal = self::surname($paternal);
        $maternal = self::surname((string) $maternal);
        $name = self::givenName($names);

        $initials = match (true) {
            // Un solo apellido: dos letras del apellido y dos del nombre
            $maternal === '' => mb_substr($paternal, 0, 2).mb_substr($name, 0, 2),
            // Apellido paterno de una o dos letras: inicial de cada apellido y dos letras del nombre
            mb_strlen($paternal) <= 2 => self::first($paternal).self::first($maternal).mb_substr($name, 0, 2),
            default => self::first($paternal).self::internalVowel($paternal).self::first($maternal).self::first($name),
        };
        $initials = str_pad($initials, 4, 'X');

        return in_array($initials, self::RFC_FORBIDDEN, true) ? mb_substr($initials, 0, 3).'X' : $initials;
    }

    /** Dígito verificador del RFC (último carácter) a partir de los anteriores; sirve para físicas (12) y morales (11). */
    public static function rfcCheckDigit(string $withoutDigit): string
    {
        $chars = mb_str_split(str_pad(mb_strtoupper($withoutDigit), 12, ' ', STR_PAD_LEFT));
        $sum = 0;
        foreach ($chars as $i => $char) {
            $value = mb_strpos(self::RFC_ALPHABET, $char);
            $sum += ($value === false ? 0 : $value) * (13 - $i);
        }
        $digit = 11 - $sum % 11;

        return match ($digit) {
            11 => '0',
            10 => 'A',
            default => (string) $digit,
        };
    }

    /** Mayúsculas sin acentos ni signos; la Ñ se conserva. */
    public static function normalize(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = strtr($value, ['Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I', 'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U']);
        $value = preg_replace('/[^A-ZÑ.\s]/u', '', $value);

        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    private static function words(string $value): array
    {
        $words = array_values(array_filter(explode(' ', self::normalize($value)), 'strlen'));
        $kept = array_values(array_filter($words, fn ($w) => ! in_array($w, self::PARTICLES, true)));

        return $kept ?: $words;
    }

    private static function surname(string $value): string
    {
        return str_replace('.', '', implode('', self::words($value)));
    }

    private static function givenName(string $value): string
    {
        $words = self::words($value);
        if (count($words) > 1 && in_array($words[0], self::SKIPPED_NAMES, true)) {
            array_shift($words);
        }

        return str_replace('.', '', $words[0] ?? '');
    }

    private static function first(string $word): string
    {
        return $word === '' ? 'X' : mb_substr($word, 0, 1);
    }

    private static function internalVowel(string $word): string
    {
        return preg_match('/^.[^AEIOU]*([AEIOU])/u', $word, $m) ? $m[1] : 'X';
    }

    private static function internalConsonant(string $word): string
    {
        return preg_match('/^.[AEIOU]*([B-DF-HJ-NP-TV-ZÑ])/u', $word, $m) ? $m[1] : 'X';
    }
}
