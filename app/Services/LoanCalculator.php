<?php

namespace App\Services;

use App\Actions\CreateQuotationTableAction;
use App\Models\LoanProduct;

/** Simulación de un crédito con las condiciones vigentes del producto: tabla de amortización y CAT. */
class LoanCalculator
{
    public function __construct(private CreateQuotationTableAction $quotation)
    {
    }

    public function simulate(float $amount, int $term, LoanProduct $product): array
    {
        $table = $this->quotation->handle($amount, $term / 12, $product->opening_fee, $product->annual_rate, $product->iva / 100);

        $rows = [];
        $openingBalance = $amount;
        $totals = ['principal' => 0.0, 'interest' => 0.0, 'iva' => 0.0, 'payment' => 0.0];

        foreach ($table['payments'] as $i => $p) {
            $rows[] = [
                'number' => $i + 1,
                'opening_balance' => round($openingBalance, 2),
                'principal' => $p['principal_payment'],
                'interest' => $p['interest_payment'],
                'iva' => $p['tax_payment'],
                'payment' => $p['monthly_payment'],
                'balance' => $p['balance'],
            ];
            $openingBalance = $p['balance'];

            $totals['principal'] += $p['principal_payment'];
            $totals['interest'] += $p['interest_payment'];
            $totals['iva'] += $p['tax_payment'];
            $totals['payment'] += $p['monthly_payment'];
        }

        $totals = array_map(fn ($v) => round($v, 2), $totals);

        return [
            'type' => $product->type,
            'product' => $product->name,
            'amount' => round($amount, 2),
            'term' => $term,
            'annual_rate' => $product->annual_rate,
            'monthly_rate' => round($product->annual_rate / 12, 2),
            'iva_rate' => $product->iva,
            'opening_fee_percent' => $product->opening_fee,
            'opening_fee_amount' => $table['opening_fee_amount'],
            'opening_fee_iva' => $table['opening_fee_tax'],
            'first_payment' => $rows[0]['payment'],
            'last_payment' => $rows[count($rows) - 1]['payment'],
            'total_to_pay' => round($totals['payment'] + $table['opening_fee_amount'] + $table['opening_fee_tax'], 2),
            'cat_sin_iva' => $table['cat_sin_iva'],
            'cat_con_iva' => $table['cat_con_iva'],
            'totals' => $totals,
            'schedule' => $rows,
            'calculated_at' => now()->toDateString(),
        ];
    }
}
