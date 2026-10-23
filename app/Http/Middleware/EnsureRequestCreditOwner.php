<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los pasos de la solicitud pública (domicilio, empleo, préstamo, documentos) solo los puede
 * ejecutar quien inició esa solicitud en esta sesión; el id de la URL por sí solo no basta.
 */
class EnsureRequestCreditOwner
{
    public const SESSION_KEY = 'request_credit.customer_id';

    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->route('customer');
        $customerId = $customer instanceof Customer ? $customer->id : (int) $customer;

        if (! $request->session()->has(self::SESSION_KEY) || (int) $request->session()->get(self::SESSION_KEY) !== $customerId) {
            return response()->json([
                'message' => 'Tu sesión de solicitud expiró. Vuelve a iniciar la solicitud desde el primer paso.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
