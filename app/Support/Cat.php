<?php

namespace App\Support;

/**
 * Costo Anual Total (CAT): tasa interna de retorno de los flujos del crédito, anualizada.
 *
 * Se resuelve la tasa mensual r tal que  desembolso neto = Σ pago_t / (1 + r)^t  y el CAT es
 * (1 + r)^12 - 1, expresado en porcentaje. El desembolso neto es el monto menos las comisiones
 * que se pagan al inicio; los pagos son las mensualidades.
 */
class Cat
{
    /**
     * @param  float  $netDisbursement  monto que recibe el cliente, ya sin comisiones iniciales
     * @param  array<int, float>  $payments  mensualidades en orden (mes 1, 2, ...)
     * @return float CAT en porcentaje, con un decimal
     */
    public static function annual(float $netDisbursement, array $payments): float
    {
        $payments = array_values($payments);
        if ($netDisbursement <= 0 || $payments === [] || array_sum($payments) <= $netDisbursement) {
            return 0.0;
        }

        $presentValue = function (float $rate) use ($payments): float {
            $total = 0.0;
            foreach ($payments as $i => $payment) {
                $total += $payment / (1 + $rate) ** ($i + 1);
            }

            return $total;
        };

        // El valor presente baja al subir la tasa: se busca por bisección entre 0 % y 1,000 % mensual
        $low = 0.0;
        $high = 10.0;
        for ($i = 0; $i < 200; $i++) {
            $mid = ($low + $high) / 2;
            if ($presentValue($mid) > $netDisbursement) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        $monthly = ($low + $high) / 2;

        return round(((1 + $monthly) ** 12 - 1) * 100, 1);
    }
}
