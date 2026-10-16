<?php

namespace App\Actions;

/**
 * Tabla de pagos con el mismo cálculo del contrato (Anexo B): interés ordinario mensual
 * (tasa anual / 12) sobre saldo insoluto, cuota fija de capital + interés e IVA sobre intereses.
 * Es la única fuente de la tabla, así el plan que ve el administrador y el Anexo B coinciden.
 */
class CreateQuotationTableAction
{
    public const IVA = 0.16;

    public function handle($amount, $years, $opening_fees, $annual_rate)
    {
        $amount = (float)$amount;
        $years = (float)$years;
        $opening_fees = (float)$opening_fees;
        $annual_rate = (float)$annual_rate;

        $annualRate = $annual_rate > 1 ? ($annual_rate / 100) : $annual_rate;
        $openingFees = $opening_fees > 1 ? ($opening_fees / 100) : $opening_fees;
        $months = max((int) round($years * 12), 1);

        $monthly_rate = $annualRate / 12;
        $fixed = $monthly_rate > 0
            ? $amount * $monthly_rate / (1 - pow(1 + $monthly_rate, -$months))
            : $amount / $months;

        $payments = [];
        $balance = $amount;
        $total = 0;

        for ($i = 1; $i <= $months; $i++) {
            $interest = round($balance * $monthly_rate, 2);
            $principal = $i === $months ? round($balance, 2) : round($fixed - $interest, 2);
            $tax = round($interest * self::IVA, 2);
            $balance = max(round($balance - $principal, 2), 0);
            $monthly_payment = round($principal + $interest + $tax, 2);
            $total += $monthly_payment;

            $payments[] = [
                'monthly_payment' => $monthly_payment,
                'principal_payment' => $principal,
                'interest_payment' => $interest,
                'tax_payment' => $tax,
                'balance' => $balance,
            ];
        }

        $to_pay = round($total, 2);
        $CAT_simple_interest = ((($amount * $openingFees) + $to_pay) - $amount) / $amount;

        return [
            'monthly_rate' => $monthly_rate,
            'to_pay_simple_interest' => $to_pay,
            'CAT_simple_interest' => $CAT_simple_interest,
            'total_months' => $months,
            'month_rate' => $monthly_rate,
            'type' => 'simple',
            'opening_fees' => $opening_fees,
            'annual_rate' => round($annualRate * 100, 2),
            'payments' => $payments,
        ];
    }
}
