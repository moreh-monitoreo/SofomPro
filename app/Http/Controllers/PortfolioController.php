<?php

namespace App\Http\Controllers;

use App\Http\Responses\JsonResponse;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanProduct;
use App\Models\PaymentDetail;
use App\Services\AuditLogger;
use App\Services\LoanCalculator;
use App\Services\LoanLedger;
use App\Services\LoanPortfolio;
use App\Services\PaymentRegistrar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Acciones del equipo sobre un préstamo: etapas del flujo, validación, desembolso y registro de pagos. */
class PortfolioController extends Controller
{
    /** Pago capturado desde una cuota: se aplica con la prelación del contrato empezando por lo más antiguo. */
    public function registerPayment(Request $request, int $id, int $detailId, PaymentRegistrar $registrar, LoanPortfolio $portfolio)
    {
        $loan = Loan::with('payment.details')->findOrFail($id);
        $detail = $this->detailOf($loan, $detailId);

        if ($detail->status === 'S') {
            return JsonResponse::error('Esa cuota ya estaba pagada.', 409);
        }

        return $this->receive($request, $loan, $registrar, $portfolio, $detail);
    }

    /** Pago general del préstamo: abono a cuotas, pago anticipado a capital o liquidación. */
    public function receivePayment(Request $request, int $id, PaymentRegistrar $registrar, LoanPortfolio $portfolio)
    {
        return $this->receive($request, Loan::with('payment.details')->findOrFail($id), $registrar, $portfolio);
    }

    private function receive(Request $request, Loan $loan, PaymentRegistrar $registrar, LoanPortfolio $portfolio, ?PaymentDetail $detail = null)
    {
        if (! $loan->approved_at) {
            return JsonResponse::error('El préstamo debe estar aprobado para registrar pagos.', 422);
        }
        if (! $loan->payment) {
            return JsonResponse::error('El préstamo no tiene plan de pagos.', 422);
        }

        $data = $request->validate([
            'method' => ['required', Rule::in(PaymentRegistrar::MANUAL_METHODS)],
            'reference' => ['nullable', 'string', 'max:120', Rule::requiredIf(in_array($request->method, ['TRANSFERENCIA', 'DEPOSITO'], true))],
            'paid_at' => 'nullable|date|before_or_equal:today',
            'amount' => [Rule::requiredIf(! $detail), 'nullable', 'numeric', 'min:0.01'],
            'kind' => ['nullable', Rule::in(array_keys(LoanPayment::KINDS))],
            'notes' => 'nullable|string|max:255',
        ], [
            'reference.required' => 'Captura la referencia o clave de rastreo del movimiento.',
            'paid_at.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'amount.required' => 'Captura el importe recibido.',
        ]);

        $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at'])->setTimeFrom(now()) : now();
        $kind = $data['kind'] ?? 'adelantado';

        try {
            $amount = isset($data['amount'])
                ? (float) $data['amount']
                : app(LoanLedger::class)->amountToCover($loan, $detail, $paidAt);
            $movement = $registrar->apply($loan, $amount, $data['method'], $data['reference'] ?? null, $paidAt, $request->user()->id, $data['notes'] ?? null, $kind);
        } catch (InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }

        AuditLogger::record('payment.registered', $movement, $loan->id, after: [
            'kind' => $kind,
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'amount' => $movement->amount,
            'allocations' => $movement->allocations,
        ], note: $data['notes'] ?? null);

        $loan->refresh()->load('payment.details');

        return JsonResponse::success([
            'detail' => $detail?->fresh(),
            'details' => $loan->payment->details->sortBy('number')->values(),
            'movement' => $movement,
            'summary' => $portfolio->summary($loan),
        ], match ($kind) {
            'liquidacion' => 'El crédito quedó liquidado.',
            'anticipado' => 'Pago anticipado aplicado; las cuotas restantes se recalcularon.',
            default => 'Pago registrado correctamente.',
        });
    }

    /** Monto para liquidar a una fecha (cláusula novena). */
    public function payoff(Request $request, int $id, LoanLedger $ledger)
    {
        $loan = Loan::with('payment.details')->findOrFail($id);
        $data = $request->validate(['date' => 'nullable|date|before_or_equal:today']);

        if (! $loan->payment) {
            return JsonResponse::error('El préstamo no tiene plan de pagos.', 422);
        }

        return JsonResponse::success($ledger->payoff($loan, Carbon::parse($data['date'] ?? today())), 'ok');
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

    /** El analista registra la consulta a Buró y el cotejo PLD (con comprobante) y deja la solicitud lista para aprobar. */
    public function validateRequest(Request $request, int $id)
    {
        $loan = Loan::findOrFail($id);

        if (! in_array($loan->stage, ['received', 'in_validation', 'validated'], true)) {
            return JsonResponse::error('Solo se puede validar una solicitud enviada que aún no se aprueba ni se rechaza.', 409);
        }

        $data = $request->validate([
            'bureau_folio' => 'required|string|max:60',
            'bureau_result' => 'required|string|max:255',
            'pld_result' => ['required', Rule::in(array_keys(Loan::PLD_RESULTS))],
            'pld_evidence' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'bureau_folio.required' => 'Captura el folio de la consulta a Buró de Crédito.',
            'bureau_result.required' => 'Captura el resultado de la consulta a Buró de Crédito.',
            'pld_result.required' => 'Indica el resultado del cotejo contra listas (PLD).',
            'pld_evidence.required' => 'Adjunta el comprobante del cotejo PLD.',
            'pld_evidence.mimes' => 'El comprobante debe ser PDF, JPG o PNG.',
            'pld_evidence.max' => 'El comprobante pesa más de 5 MB.',
        ]);

        $file = $request->file('pld_evidence');
        $path = Storage::disk(config('filesystems.documents'))
            ->putFileAs("loans/{$loan->id}/validation", $file, 'cotejo-pld-'.now()->format('YmdHis').'.'.$file->extension());

        $before = $loan->only(['bureau_folio', 'bureau_result', 'pld_result', 'validated_by']);
        $loan->forceFill([
            'validation_started_at' => $loan->validation_started_at ?? now(),
            'bureau_folio' => $data['bureau_folio'],
            'bureau_result' => $data['bureau_result'],
            'pld_result' => $data['pld_result'],
            'pld_evidence_path' => $path,
            'validated_at' => now(),
            'validated_by' => $request->user()->id,
        ])->save();

        $document = $loan->documentations()->create([
            'name' => 'Comprobante de cotejo PLD',
            'type' => 'Cotejo PLD',
            'path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);
        AuditLogger::record('document.uploaded', $document, $loan->id, note: $document->name);
        AuditLogger::record('loan.validated', $loan, $loan->id, before: array_filter($before) ?: null, after: [
            'bureau_folio' => $data['bureau_folio'],
            'bureau_result' => $data['bureau_result'],
            'pld_result' => $data['pld_result'],
        ]);

        $message = $data['pld_result'] === 'sin_coincidencias'
            ? 'Solicitud validada. Ya puede aprobarla un Gerente u otro Administrador.'
            : 'Se registró la validación con coincidencias en listas PLD: la solicitud no podrá aprobarse.';

        return JsonResponse::success(['loan' => $loan->fresh('documentations'), 'missingDocuments' => $loan->missingDocuments()], $message);
    }

    public function disburse(Request $request, int $id)
    {
        $loan = Loan::with('payment')->findOrFail($id);

        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(Loan::DISBURSEMENT_METHODS))],
            'reference' => 'required|string|max:120',
            'disbursed_at' => 'nullable|date|before_or_equal:today',
        ], [
            'method.required' => 'Indica la forma de pago del desembolso (SPEI, cheque o efectivo).',
            'reference.required' => 'Captura la referencia del desembolso (clave de rastreo, número de cheque o recibo).',
            'disbursed_at.before_or_equal' => 'La fecha del desembolso no puede ser futura.',
        ]);

        if ($loan->stage !== 'approved') {
            return JsonResponse::error('Solo se puede desembolsar una solicitud aprobada que aún no se desembolsa.', 409);
        }
        if (! $loan->payment) {
            return JsonResponse::error('Primero genera el plan de pagos del préstamo.', 422);
        }

        $loan->forceFill([
            'disbursed_at' => isset($data['disbursed_at']) ? Carbon::parse($data['disbursed_at'])->setTimeFrom(now()) : now(),
            'disbursement_method' => $data['method'],
            'disbursement_reference' => $data['reference'],
            'disbursed_by' => $request->user()->id,
        ])->save();

        $product = $loan->product();
        $fee = round((float) $loan->amount * $product->opening_fee / 100, 2);
        $net = round((float) $loan->amount - $fee - round($fee * $product->iva / 100, 2), 2);

        AuditLogger::record('loan.disbursed', $loan, $loan->id, after: [
            'disbursed_at' => (string) $loan->fresh()->disbursed_at,
            'method' => $data['method'],
            'reference' => $data['reference'],
            'net_amount' => $net,
        ]);

        return JsonResponse::success($loan->fresh(), 'Desembolso registrado por $'.number_format($net, 2).' netos.');
    }

    private function detailOf(Loan $loan, int $detailId): PaymentDetail
    {
        $detail = $loan->payment?->details->firstWhere('id', $detailId);
        abort_unless($detail, 404);

        return $detail;
    }
}
