<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuth;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las cuentas del equipo (Administrator y Advisor) deben activar la verificación en dos pasos
 * y confirmarla en cada sesión antes de usar el panel. Los clientes no se ven afectados.
 */
class EnsureTwoFactor
{
    public const SESSION_KEY = 'two_factor.verified_at';

    /** Una sesión verificada dura lo mismo que la sesión; se pide de nuevo si pasan más de 12 horas. */
    private const MAX_AGE_HOURS = 12;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! TwoFactorAuth::appliesTo($user)) {
            return $next($request);
        }

        if (! $user->two_factor_confirmed_at) {
            return $this->deny($request, 'two-factor.setup', 'Activa la verificación en dos pasos para usar el panel.');
        }

        $verifiedAt = $request->session()->get(self::SESSION_KEY);
        if (! $verifiedAt || Carbon::parse($verifiedAt)->addHours(self::MAX_AGE_HOURS)->isPast()) {
            return $this->deny($request, 'two-factor.challenge', 'Confirma tu identidad con el código de tu aplicación.');
        }

        return $next($request);
    }

    private function deny(Request $request, string $route, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'two_factor' => $route], 403);
        }

        $request->session()->put('url.intended', $request->isMethod('GET') ? $request->fullUrl() : url('/dashboard'));

        return redirect()->route($route);
    }
}
