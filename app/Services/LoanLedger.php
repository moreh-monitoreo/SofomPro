<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PaymentDetail;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cuánto se debe de cada cuota a una fecha, por concepto, con las reglas del contrato:
 * - Cláusula octava: moratorios = cantidad vencida × (tasa anual / 360) × días de atraso, más IVA.
 *   Se cobran solo cuando el atraso pasa de los días de gracia del producto; entonces cuentan desde el vencimiento.
 *   La cantidad vencida es el capital y el interés ordinario que falten de la cuota; si hubo abonos parciales,
 *   el moratorio se recalcula sobre lo que falta y se descuenta lo ya cobrado.
 * - Cláusula novena: la liquidación anticipada cubre lo exigible, el interés devengado del periodo en curso
 *   y el capital insoluto, más la penalización si se liquida antes de los meses forzosos del producto.
 */
class LoanLedger
{
    /** Orden en que se cubren los conceptos de una cuota exigible (cláusula décima cuarta). */
    public const DUE_ORDER = ['moratory_iva', 'moratory', 'iva', 'interest', 'principal'];

    /** Lo que falta de una cuota a la fecha, por concepto; incluye moratorios si está vencida. */
    public function charges(PaymentDetail $detail, LoanProduct $product, CarbonInterface $date): array
    {
        $remaining = $detail->remaining();
        $days = $this->daysLate($detail, $date);
        $moratory = 0.0;
        $moratoryIva = 0.0;

        if ($detail->status !== 'S' && $days > $product->grace_days) {
            $base = $remaining['interest'] + $remaining['principal'];
            $accrued = round($base * $product->dailyMoratoryRate() * $days, 2);
            $moratory = max(round($accrued - (float) $detail->moratory_paid, 2), 0.0);
            $moratoryIva = max(round(round($accrued * $product->iva / 100, 2) - (float) $detail->moratory_iva_paid, 2), 0.0);
        }

        if ($detail->status === 'S') {
            $remaining = ['iva' => 0.0, 'interest' => 0.0, 'principal' => 0.0];
        }

        $charges = ['moratory_iva' => $moratoryIva, 'moratory' => $moratory] + $remaining;

        return $charges + ['total' => round(array_sum($charges), 2), 'days_late' => $days];
    }

    public function daysLate(PaymentDetail $detail, CarbonInterface $date): int
    {
        $due = $detail->dueDate();
        $day = Carbon::instance($date)->startOfDay();

        return $day->gt($due) ? (int) $due->diffInDays($day) : 0;
    }

    /** Cuotas exigibles (con fecha límite igual o anterior a la fecha) que no están pagadas. */
    public function exigible(Collection $details, CarbonInterface $date): Collection
    {
        $day = Carbon::instance($date)->startOfDay();

        return $details->where('status', '!=', 'S')->filter(fn (PaymentDetail $d) => $d->dueDate()->lte($day))->sortBy('number')->values();
    }

    public function future(Collection $details, CarbonInterface $date): Collection
    {
        $day = Carbon::instance($date)->startOfDay();

        return $details->where('status', '!=', 'S')->filter(fn (PaymentDetail $d) => $d->dueDate()->gt($day))->sortBy('number')->values();
    }

    /** Importe para dejar pagada la cuota indicada (y las anteriores pendientes) a la fecha. */
    public function amountToCover(Loan $loan, PaymentDetail $detail, CarbonInterface $date): float
    {
        $product = $loan->product();

        return round($loan->payment->details
            ->where('status', '!=', 'S')
            ->where('number', '<=', $detail->number)
            ->sum(fn (PaymentDetail $d) => $this->charges($d, $product, $date)['total']), 2);
    }

    /** Monto para liquidar el crédito a la fecha, desglosado. */
    public function payoff(Loan $loan, CarbonInterface $date): array
    {
        $loan->loadMissing('payment.details');
        $product = $loan->product();
        $ivaRate = $product->iva / 100;
        $details = $loan->payment?->details->sortBy('number')->values() ?? collect();

        $exigible = $this->exigible($details, $date);
        $exigibleTotal = round($exigible->sum(fn ($d) => $this->charges($d, $product, $date)['total']), 2);

        $future = $this->future($details, $date);
        $current = $future->first();
        $accruedInterest = 0.0;
        $accruedIva = 0.0;
        if ($current) {
            [$interest, $iva] = $this->accruedOfPeriod($loan, $details, $current, $date, $ivaRate);
            $accruedInterest = $interest;
            $accruedIva = $iva;
        }

        $principal = round($future->sum(fn ($d) => $d->remaining()['principal']), 2);

        $penalty = 0.0;
        if ($product->min_forced_months > 0 && $principal > 0) {
            $start = Carbon::parse($loan->disbursed_at ?? $loan->approved_at ?? $date);
            if ($start->copy()->addMonthsNoOverflow($product->min_forced_months)->gt($date)) {
                $penalty = round($principal * $product->early_payoff_penalty / 100, 2);
            }
        }
        $penaltyIva = round($penalty * $ivaRate, 2);

        return [
            'date' => Carbon::instance($date)->toDateString(),
            'exigible' => $exigibleTotal,
            'accrued_interest' => $accruedInterest,
            'accrued_iva' => $accruedIva,
            'principal' => $principal,
            'penalty' => $penalty,
            'penalty_iva' => $penaltyIva,
            'total' => round($exigibleTotal + $accruedInterest + $accruedIva + $principal + $penalty + $penaltyIva, 2),
        ];
    }

    /**
     * Interés ordinario (y su IVA) devengado del periodo en curso hasta la fecha, menos lo ya abonado.
     *
     * @return array{0: float, 1: float}
     */
    public function accruedOfPeriod(Loan $loan, Collection $details, PaymentDetail $current, CarbonInterface $date, float $ivaRate): array
    {
        $previous = $details->where('number', $current->number - 1)->first();
        $start = $previous
            ? $previous->dueDate()
            : Carbon::parse($loan->disbursed_at ?? $loan->approved_at ?? $current->dueDate()->copy()->subMonthNoOverflow())->startOfDay();
        $end = $current->dueDate();
        $day = Carbon::instance($date)->startOfDay();

        $periodDays = max((int) $start->diffInDays($end), 1);
        $elapsed = min(max((int) $start->diffInDays($day, false), 0), $periodDays);

        $interest = round((float) $current->interest_payment * $elapsed / $periodDays, 2);
        $iva = round($interest * $ivaRate, 2);

        return [
            max(round($interest - (float) $current->interest_paid, 2), 0.0),
            max(round($iva - (float) $current->iva_paid, 2), 0.0),
        ];
    }
}
