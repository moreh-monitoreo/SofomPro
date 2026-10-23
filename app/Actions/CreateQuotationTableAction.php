<?php

namespace App\Actions;

use App\Support\Cat;

/**
 * Tabla de pagos con el mismo cálculo del contrato (Anexo B): interés ordinario mensual
 * (tasa anual / 12) sobre saldo insoluto, cuota fija de capital + interés e IVA sobre intereses.
 * Es la única fuente de la tabla, así el plan que ve el administrador y el Anexo B coinciden.
 */
class CreateQuotationTableAction
{
    public const IVA = 0.16;

    public function handle($amount, $years, $opening_fees, $annual_rate, ?float $iva = null)
    {
        $amount = (float)$amount;
        $years = (float)$years;
        $opening_fees = (float)$opening_fees;
        $annual_rate = (float)$annual_rate;
        $ivaRate = $iva ?? self::IVA;

        $annualRate = $annual_rate > 1 ? ($annual_rate / 100) : $annual_rate;
        $openingFees = $opening_fees / 100; // siempre en porcentaje (3 = 3 %)
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
            $tax = round($interest * $ivaRate, 2);
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

        // CAT real (TIR anualizada): la comisión por apertura se paga al inicio y su IVA se suma al costo con IVA
        $openingFee = $amount * $openingFees;
        $openingFeeTax = $openingFee * $ivaRate;
        $catWithoutTax = Cat::annual($amount - $openingFee, array_map(fn ($p) => $p['principal_payment'] + $p['interest_payment'], $payments));
        $catWithTax = Cat::annual($amount - $openingFee - $openingFeeTax, array_column($payments, 'monthly_payment'));

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
            'iva_rate' => round($ivaRate * 100, 2),
            'opening_fee_amount' => round($openingFee, 2),
            'opening_fee_tax' => round($openingFeeTax, 2),
            'cat_sin_iva' => $catWithoutTax,
            'cat_con_iva' => $catWithTax,
            'payments' => $payments,
        ];
    }
}
