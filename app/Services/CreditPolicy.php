<?php

namespace App\Services;

use App\Models\Loan;

/**
 * Política de crédito del producto. Cada regla compara un dato del expediente contra el límite del producto;
 * un límite en 0 desactiva la regla. Si alguna no se cumple, aprobar exige una justificación del Gerente.
 *
 * - Capacidad de pago: primera cuota / (ingresos + otros ingresos − egresos) ≤ max_debt_ratio %.
 * - Antigüedad en el empleo y en el domicilio: años capturados en la solicitud ≥ mínimo.
 * - Buró: calificación capturada por el analista ≥ mínimo.
 */
class CreditPolicy
{
    public function __construct(private LoanCalculator $calculator)
    {
    }

    /**
     * @return array{passes: bool, rules: list<array{key: string, label: string, value: string, limit: string, passes: bool}>}
     */
    public function evaluate(Loan $loan, ?float $amount = null, ?int $term = null): array
    {
        $loan->loadMissing('customer.employment', 'customer.addresses');
        $product = $loan->product();
        $job = $loan->customer?->employment;
        $rules = [];

        if ($product->max_debt_ratio > 0) {
            $amount ??= (float) $loan->amount;
            $term ??= (int) $loan->term;
            $payment = $amount > 0 && $term > 0 ? $this->calculator->simulate($amount, $term, $product)['first_payment'] : null;
            $income = (float) ($job->monthly_income ?? 0) + (float) ($job->other_income ?? 0);
            $available = $income - (float) ($job->monthly_expenses ?? 0);
            $ratio = $payment !== null && $available > 0 ? round($payment / $available * 100, 1) : null;

            $rules[] = [
                'key' => 'capacity',
                'label' => 'Capacidad de pago',
                'value' => match (true) {
                    $payment === null => 'Sin monto o plazo',
                    $income <= 0 => 'Sin ingresos capturados',
                    $available <= 0 => 'Egresos iguales o mayores a los ingresos',
                    default => "La cuota es el {$ratio}% del ingreso disponible",
                },
                'limit' => 'Máximo '.$this->number($product->max_debt_ratio).'%',
                'passes' => $ratio !== null && $ratio <= $product->max_debt_ratio,
            ];
        }

        if ($product->min_employment_years > 0) {
            $years = $job?->seniority_working;
            $rules[] = [
                'key' => 'employment',
                'label' => 'Antigüedad en el empleo',
                'value' => $years === null ? 'Sin capturar' : $this->years((int) $years),
                'limit' => 'Mínimo '.$this->years($product->min_employment_years),
                'passes' => $years !== null && (int) $years >= $product->min_employment_years,
            ];
        }

        if ($product->min_residence_years > 0) {
            $years = $loan->customer?->addresses->sortByDesc('id')->first()?->years_living;
            $rules[] = [
                'key' => 'residence',
                'label' => 'Antigüedad en el domicilio',
                'value' => $years === null ? 'Sin capturar' : $this->years((int) $years),
                'limit' => 'Mínimo '.$this->years($product->min_residence_years),
                'passes' => $years !== null && (int) $years >= $product->min_residence_years,
            ];
        }

        if ($product->min_bureau_score > 0) {
            $score = $loan->bureau_score;
            $rules[] = [
                'key' => 'bureau',
                'label' => 'Calificación de Buró',
                'value' => $score === null ? 'Sin capturar' : (string) $score,
                'limit' => 'Mínimo '.$product->min_bureau_score,
                'passes' => $score !== null && (int) $score >= $product->min_bureau_score,
            ];
        }

        return [
            'passes' => collect($rules)->every(fn ($rule) => $rule['passes']),
            'rules' => $rules,
        ];
    }

    private function years(int $years): string
    {
        return $years === 1 ? '1 año' : "{$years} años";
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
