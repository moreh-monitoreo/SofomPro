<?php

namespace App\Services;

use App\Models\PaymentDetail;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Único punto donde una cuota pasa a pagada o se revierte, venga de Stripe o de un pago manual.
 * Mantiene el saldo del cliente y el estado del préstamo coherentes.
 */
class PaymentRegistrar
{
    public const MANUAL_METHODS = ['TRANSFERENCIA', 'DEPOSITO', 'EFECTIVO', 'OTRO'];

    /**
     * @return bool true si la cuota quedó pagada por esta llamada; false si ya lo estaba
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
        return DB::transaction(function () use ($detail, $method, $reference, $amountReceived, $paidAt, $registeredBy, $notes) {
            $locked = PaymentDetail::lockForUpdate()->findOrFail($detail->id);
            if ($locked->status === 'S') {
                return false;
            }

            $amount = $amountReceived ?? (float) $locked->monthly_payment;
            if (round($amount, 2) < round((float) $locked->monthly_payment, 2)) {
                throw new InvalidArgumentException('El monto recibido es menor a la cuota; no se aceptan pagos parciales.');
            }

            $locked->forceFill([
                'status' => 'S',
                'payed_at' => $paidAt ?? now(),
                'payment_method' => $method,
                'payment_reference' => $reference,
                'payment_total' => round($amount, 2),
                'registered_by' => $registeredBy,
                'notes' => $notes,
            ])->save();

            $this->syncLoan($locked);
            $detail->refresh();

            return true;
        });
    }

    /** Deshace un pago registrado por error. Solo pagos manuales: un cobro de Stripe se devuelve en Stripe. */
    public function revert(PaymentDetail $detail): void
    {
        DB::transaction(function () use ($detail) {
            $locked = PaymentDetail::lockForUpdate()->findOrFail($detail->id);

            if ($locked->status !== 'S') {
                throw new InvalidArgumentException('La cuota no está pagada.');
            }
            if ($locked->payment_method === 'STRIPE') {
                throw new InvalidArgumentException('Un pago con tarjeta no se puede revertir aquí; devuélvelo desde Stripe.');
            }

            $locked->forceFill([
                'status' => 'N',
                'payed_at' => null,
                'payment_method' => null,
                'payment_reference' => null,
                'payment_total' => null,
                'registered_by' => null,
                'notes' => null,
            ])->save();

            $this->syncLoan($locked);
            $detail->refresh();
        });
    }

    /** El préstamo queda liquidado cuando todas sus cuotas están pagadas. */
    private function syncLoan(PaymentDetail $detail): void
    {
        $payment = $detail->payment()->with('loan')->first();
        $allPaid = ! $payment->details()->where('status', '!=', 'S')->exists();

        $payment->forceFill(['status' => $allPaid ? 'P' : 'N'])->save();
        $payment->loan->forceFill(['paid_at' => $allPaid ? ($detail->payed_at ?? now()) : null])->save();
    }
}
