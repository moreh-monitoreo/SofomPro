<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Cierra la sesión de una cuenta que un administrador desactivó mientras estaba dentro. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tu cuenta está desactivada.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta está desactivada. Comunícate con el administrador.']);
        }

        return $next($request);
    }
}
