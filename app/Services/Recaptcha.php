<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Verificación del lado del servidor de Google reCAPTCHA v2. Si no hay llaves configuradas, queda desactivado. */
class Recaptcha
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public static function enabled(): bool
    {
        return filled(config('promas.recaptcha.secret'));
    }

    public function passes(?string $token, ?string $ip = null): bool
    {
        if (! self::enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(8)->post(self::VERIFY_URL, array_filter([
                'secret' => config('promas.recaptcha.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (\Throwable $e) {
            Log::warning('No se pudo verificar reCAPTCHA: '.$e->getMessage());

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
