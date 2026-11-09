<?php

namespace App\Actions\Loans;

use App\Actions\CreateQuotationTableAction;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Plan de pagos con las condiciones aprobadas (monto y plazo del préstamo) y la tasa, comisión e IVA del producto.
 * No hay captura libre: así el plan, la carátula y la tabla de amortización del contrato siempre coinciden.
 * La primera cuota vence en la fecha de inicio de pagos (la misma que usa el Anexo B).
 */
class CreatePaymentPlanAction
{
    public function __construct(private CreateQuotationTableAction $quotation)
    {
    }

    /** Solo calcula la tabla, sin guardarla. */
    public function preview(Loan $loan): array
    {
        $product = $loan->product();

        return $this->quotation->handle((float) $loan->amount, (int) $loan->term / 12, $product->opening_fee, $product->annual_rate, $product->iva / 100);
    }

    public function handle(Loan $loan): Payment
    {
        if (! $loan->approved_at) {
            throw new InvalidArgumentException('El préstamo debe estar aprobado para generar su plan de pagos.');
        }
        if (! $loan->amount || ! $loan->term) {
            throw new InvalidArgumentException('El préstamo no tiene monto o plazo definidos.');
        }

        return DB::transaction(function () use ($loan) {
            $loan->load('payment.details');

            // Generar de nuevo reemplaza el plan anterior, salvo que ya tenga pagos aplicados
            if ($loan->payment) {
                if ($loan->movements()->whereNull('reverted_at')->exists() || $loan->payment->details->where('status', '!=', 'N')->isNotEmpty()) {
                    throw new InvalidArgumentException('El plan ya tiene pagos registrados y no se puede reemplazar.');
                }
                $loan->payment->details()->delete();
                $loan->payment->delete();
            }

            $table = $this->preview($loan);
            $start = Carbon::parse($loan->start_at ?? Carbon::parse($loan->approved_at)->addMonthNoOverflow()->firstOfMonth());

            $payment = Payment::create([
                'loan_id' => $loan->id,
                'request_amount' => $loan->amount,
                'payment_amount' => $table['to_pay_simple_interest'],
                'cat_type' => 'irr',
                'cat_amount' => $table['cat_con_iva'] / 100,
                'cat_sin_iva' => $table['cat_sin_iva'] / 100,
                'total_months' => $table['total_months'],
                'month_rate' => $table['month_rate'],
                'opening_fees' => $table['opening_fees'],
                'status' => 'N',
            ]);

            foreach ($table['payments'] as $index => $pay) {
                $due = $start->copy()->addMonthsNoOverflow($index);

                PaymentDetail::create([
                    'payment_id' => $payment->id,
                    'number' => $index + 1,
                    'month' => $due->format('m'),
                    'year' => $due->year,
                    'due_date' => $due->toDateString(),
                    'interest_payment' => $pay['interest_payment'],
                    'principal_payment' => $pay['principal_payment'],
                    'balance' => $pay['balance'],
                    'monthly_payment' => $pay['monthly_payment'],
                    'status' => 'N',
                ]);
            }

            return $payment->load('details');
        });
    }
}
