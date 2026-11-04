<?php

namespace App\Http\Controllers;

use App\Http\Responses\JsonResponse;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PaymentDetail;
use App\Services\AuditLogger;
use App\Services\LoanCalculator;
use App\Services\LoanPortfolio;
use App\Services\PaymentRegistrar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Acciones del equipo sobre un préstamo: etapas del flujo y registro de pagos manuales. */
class PortfolioController extends Controller
{
    public function registerPayment(Request $request, int $id, int $detailId, PaymentRegistrar $registrar, LoanPortfolio $portfolio)
    {
        $loan = Loan::with('payment.details')->findOrFail($id);
        $detail = $this->detailOf($loan, $detailId);

        if (! $loan->approved_at) {
            return JsonResponse::error('El préstamo debe estar aprobado para registrar pagos.', 422);
        }

        $data = $request->validate([
            'method' => ['required', Rule::in(PaymentRegistrar::MANUAL_METHODS)],
            'reference' => ['nullable', 'string', 'max:120', Rule::requiredIf(in_array($request->method, ['TRANSFERENCIA', 'DEPOSITO'], true))],
            'paid_at' => 'nullable|date|before_or_equal:today',
            'amount' => 'nullable|numeric|min:0.01',
            'notes' => 'nullable|string|max:255',
        ], [
            'reference.required' => 'Captura la referencia o clave de rastreo del movimiento.',
            'paid_at.before_or_equal' => 'La fecha de pago no puede ser futura.',
        ]);

        try {
            $registered = $registrar->markPaid(
                $detail,
                $data['method'],
                $data['reference'] ?? null,
                isset($data['amount']) ? (float) $data['amount'] : null,
                isset($data['paid_at']) ? \Carbon\Carbon::parse($data['paid_at'])->setTimeFrom(now()) : null,
                $request->user()->id,
                $data['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }

        if (! $registered) {
            return JsonResponse::error('Esa cuota ya estaba pagada.', 409);
        }

        AuditLogger::record('payment.registered', $detail, $loan->id, after: [
            'number' => $detail->number,
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'amount' => isset($data['amount']) ? (float) $data['amount'] : (float) $detail->monthly_payment,
        ], note: $data['notes'] ?? null);

        $loan->refresh()->load('payment.details');

        return JsonResponse::success([
            'detail' => $detail->fresh(),
            'summary' => $portfolio->summary($loan),
        ], 'Pago registrado correctamente.');
    }

    /** Vista previa interna de las condiciones que se podrían aprobar (monto y plazo libres dentro de los límites del producto). */
    public function preview(Request $request, int $id, LoanCalculator $calculator)
    {
        $loan = Loan::findOrFail($id);
        $product = LoanProduct::forType($loan->type);

        $data = $request->validate([
            'amount' => "required|numeric|min:{$product->min_amount}|max:{$product->max_amount}",
            'term' => "required|integer|min:{$product->min_term}|max:{$product->max_term}",
        ], [
            'amount.min' => 'El monto mínimo del producto es de $'.number_format($product->min_amount).'.',
            'amount.max' => 'El monto máximo del producto es de $'.number_format($product->max_amount).'.',
            'term.min' => "El plazo mínimo del producto es de {$product->min_term} meses.",
            'term.max' => "El plazo máximo del producto es de {$product->max_term} meses.",
        ]);

        return JsonResponse::success($calculator->simulate((float) $data['amount'], (int) $data['term'], $product), 'ok');
    }

    public function startValidation(int $id)
    {
        $loan = Loan::findOrFail($id);

        if ($loan->stage !== 'received') {
            return JsonResponse::error('La solicitud ya está en otra etapa: '.$loan->stage_label.'.', 409);
        }

        $loan->forceFill(['validation_started_at' => now()])->save();
        AuditLogger::record('loan.validation_started', $loan, $loan->id);

        return JsonResponse::success($loan->fresh(), 'La solicitud pasó a validación.');
    }

    public function disburse(Request $request, int $id)
    {
        $loan = Loan::with('payment')->findOrFail($id);

        $data = $request->validate(['disbursed_at' => 'nullable|date|before_or_equal:today']);

        if ($loan->stage !== 'approved') {
            return JsonResponse::error('Solo se puede desembolsar una solicitud aprobada que aún no se desembolsa.', 409);
        }
        if (! $loan->payment) {
            return JsonResponse::error('Primero guarda el plan de pagos del préstamo.', 422);
        }

        $loan->forceFill([
            'disbursed_at' => isset($data['disbursed_at']) ? \Carbon\Carbon::parse($data['disbursed_at'])->setTimeFrom(now()) : now(),
        ])->save();
        AuditLogger::record('loan.disbursed', $loan, $loan->id, after: ['disbursed_at' => (string) $loan->fresh()->disbursed_at]);

        return JsonResponse::success($loan->fresh(), 'Desembolso registrado.');
    }

    private function detailOf(Loan $loan, int $detailId): PaymentDetail
    {
        $detail = $loan->payment?->details->firstWhere('id', $detailId);
        abort_unless($detail, 404);

        return $detail;
    }
}
