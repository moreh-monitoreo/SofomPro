<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Único punto donde se aplica o se revierte un pago, venga de Stripe o de un pago manual.
 * Aplica cada pago con la prelación del contrato (cláusula décima cuarta) y guarda el estado previo
 * de las cuotas para poder revertirlo. Mantiene el saldo del cliente y el estado del préstamo coherentes.
 *
 * - adelantado: cubre lo exigible y el sobrante se aplica a las cuotas siguientes (pago adelantado).
 * - anticipado: cubre lo exigible y el sobrante reduce el capital insoluto; las cuotas restantes se recalculan
 *   con el mismo plazo (cláusula novena: al corriente y por al menos una parcialidad).
 * - liquidacion: paga el total para liquidar a la fecha ({@see LoanLedger::payoff()}).
 */
class PaymentRegistrar
{
    public const MANUAL_METHODS = ['TRANSFERENCIA', 'DEPOSITO', 'EFECTIVO', 'OTRO'];

    private const SNAPSHOT_COLUMNS = [
        'status', 'payed_at', 'payment_method', 'payment_reference', 'payment_total', 'registered_by', 'notes',
        'moratory_iva_paid', 'moratory_paid', 'iva_paid', 'interest_paid', 'principal_paid',
        'interest_payment', 'principal_payment', 'monthly_payment', 'balance',
    ];

    private const COLUMN = [
        'moratory_iva' => 'moratory_iva_paid',
        'moratory' => 'moratory_paid',
        'iva' => 'iva_paid',
        'interest' => 'interest_paid',
        'principal' => 'principal_paid',
    ];

    public function __construct(private LoanLedger $ledger)
    {
    }

    public function apply(
        Loan $loan,
        float $amount,
        string $method,
        ?string $reference = null,
        ?CarbonInterface $paidAt = null,
        ?int $registeredBy = null,
        ?string $notes = null,
        string $kind = 'adelantado',
    ): LoanPayment {
        if (! array_key_exists($kind, LoanPayment::KINDS)) {
            throw new InvalidArgumentException('Tipo de pago no válido.');
        }
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('El importe debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($loan, $amount, $method, $reference, $paidAt, $registeredBy, $notes, $kind) {
            $payment = Payment::lockForUpdate()->where('loan_id', $loan->id)->first();
            if (! $payment) {
                throw new InvalidArgumentException('El préstamo no tiene plan de pagos.');
            }
            $loan->setRelation('payment', $payment);
            $details = $payment->details()->orderBy('number')->get();
            $payment->setRelation('details', $details);

            if ($details->every(fn ($d) => $d->status === 'S')) {
                throw new InvalidArgumentException('El préstamo ya está liquidado.');
            }

            $date = Carbon::instance($paidAt ?? now());
            $product = $loan->product();
            $snapshot = [
                'prepaid_principal' => (float) $payment->prepaid_principal,
                'details' => $details->map(fn ($d) => ['id' => $d->id] + $d->only(self::SNAPSHOT_COLUMNS))->all(),
            ];

            $allocations = ['details' => [], 'penalty' => 0.0, 'penalty_iva' => 0.0, 'principal_prepayment' => 0.0];
            $available = $amount;

            if ($kind === 'liquidacion') {
                $payoff = $this->ledger->payoff($loan, $date);
                if (abs($amount - $payoff['total']) > 0.005) {
                    throw new InvalidArgumentException('Para liquidar a esta fecha el importe debe ser exactamente $'.number_format($payoff['total'], 2).'.');
                }
                $available = $this->allocateExigible($details, $product, $date, $available, $allocations);
                $this->settleFuture($loan, $details, $date, $product->iva / 100, $payoff, $allocations);
                $available = 0.0;
            } else {
                $available = $this->allocateExigible($details, $product, $date, $available, $allocations);
                $future = $this->ledger->future($details, $date);

                if ($kind === 'anticipado') {
                    $this->prepayPrincipal($payment, $details, $date, $available, $product->iva / 100, $allocations);
                    $available = 0.0;
                } elseif ($available > 0) {
                    foreach ($future as $detail) {
                        foreach (['iva', 'interest', 'principal'] as $concept) {
                            $available = $this->take($detail, $concept, $detail->remaining()[$concept], $available, $allocations);
                        }
                    }
                }

                if ($available > 0.005) {
                    $payoff = $this->ledger->payoff($loan, $date);
                    throw new InvalidArgumentException('El importe excede lo que se debe en las cuotas. Para terminar el crédito usa la liquidación anticipada por $'.number_format($payoff['total'], 2).'.');
                }
            }

            foreach ($details as $detail) {
                $applied = $allocations['details'][$detail->id] ?? null;
                if (! $applied) {
                    continue;
                }
                $detail->payment_total = round((float) $detail->payment_total + array_sum(array_intersect_key($applied, self::COLUMN + ['penalty' => 0, 'penalty_iva' => 0])), 2);
                if ($detail->status !== 'S' && $detail->remainingTotal() <= 0.0) {
                    $detail->status = 'S';
                }
                if ($detail->status === 'S' && $detail->isDirty('status')) {
                    $detail->forceFill([
                        'payed_at' => $date,
                        'payment_method' => $method,
                        'payment_reference' => $reference,
                        'registered_by' => $registeredBy,
                        'notes' => $kind === 'liquidacion' ? trim('Liquidación anticipada. '.$notes) : $notes,
                    ]);
                }
                $detail->save();
            }

            $movement = LoanPayment::create([
                'loan_id' => $loan->id,
                'kind' => $kind,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => $date,
                'registered_by' => $registeredBy,
                'notes' => $notes,
                'allocations' => $allocations,
                'snapshot' => $snapshot,
            ]);

            $this->syncLoan($payment, $date);

            return $movement;
        });
    }

    /**
     * Pago de una cuota (Stripe o captura por cuota). Si no se indica importe, se cobra lo necesario para dejarla
     * pagada, con sus moratorios y las anteriores pendientes.
     *
     * @return bool true si el pago se aplicó; false si la cuota ya estaba pagada o el pago ya se había registrado
     */
    public function markPaid(
        PaymentDetail $detail,
        string $method,
        ?string $reference = null,
        ?float $amountReceived = null,
        ?CarbonInterface $paidAt = null,
        ?int $registeredBy = null,
        ?string $notes = null,
    ): bool {
        $detail->refresh();
        if ($detail->status === 'S') {
            return false;
        }

        $loan = $detail->payment->loan;
        // Stripe puede reenviar el mismo evento: el mismo cobro no se aplica dos veces
        if ($method === 'STRIPE' && $reference && LoanPayment::where('loan_id', $loan->id)->where('method', $method)->where('reference', $reference)->whereNull('reverted_at')->exists()) {
            return false;
        }

        $amount = $amountReceived ?? $this->ledger->amountToCover($loan->load('payment.details'), $detail, $paidAt ?? now());
        $this->apply($loan, $amount, $method, $reference, $paidAt, $registeredBy, $notes);
        $detail->refresh();

        return true;
    }

    /** Deshace un pago registrado por error; solo el más reciente del préstamo y nunca uno de Stripe. */
    public function revert(LoanPayment $movement, ?int $revertedBy = null): void
    {
        DB::transaction(function () use ($movement, $revertedBy) {
            $payment = Payment::lockForUpdate()->where('loan_id', $movement->loan_id)->firstOrFail();
            $movement->refresh();

            if ($movement->reverted_at) {
                throw new InvalidArgumentException('Ese pago ya fue revertido.');
            }
            if ($movement->method === 'STRIPE') {
                throw new InvalidArgumentException('Un pago con tarjeta no se puede revertir aquí; devuélvelo desde Stripe.');
            }
            $latest = LoanPayment::where('loan_id', $movement->loan_id)->whereNull('reverted_at')->latest('id')->first();
            if ($latest && $latest->id !== $movement->id) {
                throw new InvalidArgumentException('Primero revierte el pago más reciente del préstamo.');
            }

            foreach ($movement->snapshot['details'] as $row) {
                PaymentDetail::whereKey($row['id'])->update(collect($row)->except('id')->all());
            }
            $payment->forceFill(['prepaid_principal' => $movement->snapshot['prepaid_principal'] ?? 0])->save();
            $movement->forceFill(['reverted_at' => now(), 'reverted_by' => $revertedBy])->save();

            $this->syncLoan($payment->fresh(), now());
        });
    }

    /** Revierte el pago que cubrió una cuota. Las cuotas pagadas antes de registrar abonos se reabren directamente. */
    public function revertDetail(PaymentDetail $detail, ?int $revertedBy = null): void
    {
        $detail->refresh();
        $loanId = $detail->payment->loan_id;
        $latest = LoanPayment::where('loan_id', $loanId)->whereNull('reverted_at')->latest('id')->first();

        if ($latest) {
            if (! array_key_exists($detail->id, $latest->allocations['details'] ?? [])) {
                throw new InvalidArgumentException('Primero revierte el pago más reciente del préstamo.');
            }
            $this->revert($latest, $revertedBy);
            $detail->refresh();

            return;
        }

        if ($detail->status !== 'S') {
            throw new InvalidArgumentException('La cuota no está pagada.');
        }
        if ($detail->payment_method === 'STRIPE') {
            throw new InvalidArgumentException('Un pago con tarjeta no se puede revertir aquí; devuélvelo desde Stripe.');
        }

        DB::transaction(function () use ($detail) {
            $detail->forceFill([
                'status' => 'N', 'payed_at' => null, 'payment_method' => null, 'payment_reference' => null,
                'payment_total' => null, 'registered_by' => null, 'notes' => null,
                'moratory_iva_paid' => 0, 'moratory_paid' => 0, 'iva_paid' => 0, 'interest_paid' => 0, 'principal_paid' => 0,
            ])->save();
            $this->syncLoan($detail->payment, now());
        });
        $detail->refresh();
    }

    /**
     * Cubre lo exigible de la cuota más antigua a la más reciente; dentro de cada cuota, en el orden de la cláusula
     * décima cuarta: IVA de moratorios, moratorios, IVA de intereses, intereses y capital.
     */
    private function allocateExigible(Collection $details, $product, CarbonInterface $date, float $available, array &$allocations): float
    {
        foreach ($this->ledger->exigible($details, $date) as $detail) {
            $charges = $this->ledger->charges($detail, $product, $date);
            foreach (LoanLedger::DUE_ORDER as $concept) {
                $available = $this->take($detail, $concept, $charges[$concept], $available, $allocations);
            }
        }

        return $available;
    }

    private function take(PaymentDetail $detail, string $concept, float $due, float $available, array &$allocations): float
    {
        $amount = round(min($due, $available), 2);
        if ($amount <= 0) {
            return $available;
        }

        $column = self::COLUMN[$concept];
        $detail->{$column} = round((float) $detail->{$column} + $amount, 2);
        $allocations['details'][$detail->id]['number'] = $detail->number;
        $allocations['details'][$detail->id][$concept] = round(($allocations['details'][$detail->id][$concept] ?? 0) + $amount, 2);

        return round($available - $amount, 2);
    }

    private function prepayPrincipal(Payment $payment, Collection $details, CarbonInterface $date, float $amount, float $ivaRate, array &$allocations): void
    {
        if ($this->ledger->exigible($details, $date)->contains(fn ($d) => $d->remainingTotal() > 0)) {
            throw new InvalidArgumentException('Para un pago anticipado el cliente debe estar al corriente: el importe no alcanza para cubrir lo vencido.');
        }

        $future = $this->ledger->future($details, $date);
        if ($future->contains(fn ($d) => (float) $d->iva_paid + (float) $d->interest_paid + (float) $d->principal_paid > 0)) {
            throw new InvalidArgumentException('Hay cuotas futuras con abonos adelantados; revierte esos abonos o registra el pago como adelantado.');
        }
        if ($amount < (float) $future->first()->monthly_payment) {
            throw new InvalidArgumentException('El pago anticipado debe ser al menos de una parcialidad ($'.number_format((float) $future->first()->monthly_payment, 2).').');
        }

        $balance = round($future->sum(fn ($d) => (float) $d->principal_payment) - $amount, 2);
        if ($balance <= 0) {
            throw new InvalidArgumentException('El importe cubre todo el capital: registra una liquidación anticipada.');
        }

        // Mismo cálculo que la tabla original: cuota fija sobre el nuevo saldo, con el mismo número de cuotas restantes
        $rate = (float) $payment->month_rate;
        $months = $future->count();
        $fixed = $rate > 0 ? $balance * $rate / (1 - pow(1 + $rate, -$months)) : $balance / $months;

        foreach ($future->values() as $i => $detail) {
            $interest = round($balance * $rate, 2);
            $principal = $i === $months - 1 ? round($balance, 2) : round($fixed - $interest, 2);
            $balance = max(round($balance - $principal, 2), 0);
            $detail->forceFill([
                'interest_payment' => $interest,
                'principal_payment' => $principal,
                'balance' => $balance,
                'monthly_payment' => round($principal + $interest + round($interest * $ivaRate, 2), 2),
            ])->save();
        }

        $payment->prepaid_principal = round((float) $payment->prepaid_principal + $amount, 2);
        $payment->save();
        $allocations['principal_prepayment'] = $amount;
    }

    private function settleFuture(Loan $loan, Collection $details, CarbonInterface $date, float $ivaRate, array $payoff, array &$allocations): void
    {
        $future = $this->ledger->future($details, $date);
        $current = $future->first();
        if (! $current) {
            return;
        }

        [$interest, $iva] = $this->ledger->accruedOfPeriod($loan, $details, $current, $date, $ivaRate);
        $this->take($current, 'iva', $iva, $iva, $allocations);
        $this->take($current, 'interest', $interest, $interest, $allocations);

        foreach ($future as $detail) {
            $principal = $detail->remaining()['principal'];
            $this->take($detail, 'principal', $principal, $principal, $allocations);
            // El interés de periodos que aún no corren no se cobra
            $detail->status = 'S';
        }

        $allocations['penalty'] = $payoff['penalty'];
        $allocations['penalty_iva'] = $payoff['penalty_iva'];
        $allocations['details'][$current->id]['number'] = $current->number;
        $allocations['details'][$current->id]['penalty'] = $payoff['penalty'];
        $allocations['details'][$current->id]['penalty_iva'] = $payoff['penalty_iva'];
    }

    /** El préstamo queda liquidado cuando todas sus cuotas están pagadas. */
    private function syncLoan(Payment $payment, CarbonInterface $date): void
    {
        $allPaid = ! $payment->details()->where('status', '!=', 'S')->exists();
        $lastPaid = $payment->details()->where('status', 'S')->max('payed_at');

        $payment->forceFill(['status' => $allPaid ? 'P' : 'N'])->save();
        $payment->loan->forceFill(['paid_at' => $allPaid ? ($lastPaid ?? $date) : null])->save();
    }
}
