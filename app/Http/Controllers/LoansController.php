<?php

namespace App\Http\Controllers;

use App\Actions\Loans\CreateCustomerAccountAction;
use App\Actions\Loans\CreatePaymentPlanAction;
use App\Actions\Loans\GenerateLoanDocumentsAction;
use App\Services\MorehContractService;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\AuditLogger;
use App\Services\LoanPortfolio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Actions\Global\Files\UploadFileAction;
use InvalidArgumentException;

class LoansController extends Controller
{
    public function index()
    {
        // Solo los campos que muestra la tabla: el expediente completo (CURP, RFC, ingresos) va en el detalle
        $loans = Loan::with('customer:id,name,second_name,last_name,maiden_name')->withCount('payment')->orderBy('id', 'desc')->paginate()
            ->through(fn (Loan $loan) => [
                'id' => $loan->id,
                'customer' => ['id' => $loan->customer->id, 'full_name' => $loan->customer->full_name],
                'amount' => $loan->amount,
                'term' => $loan->term,
                'purpose' => $loan->purpose,
                'stage' => $loan->stage,
                'stage_label' => $loan->stage_label,
                'approved_at' => $loan->approved_at,
                'reject_at' => $loan->reject_at,
                'paid_at' => $loan->paid_at,
                'payment_count' => $loan->payment_count,
            ]);

        return Inertia::render('Loans/LoansAgentView', [
            'loans' => $loans,
            'message' => session('message'),
        ]);
    }

    public function show(Request $request, $id)
    {
        $loan = Loan::with(['customer.addresses', 'customer.employment', 'customer.references', 'documentations'])->findOrFail($id);
        $user = $request->user();
        $product = $loan->product();

        return Inertia::render('Loans/LoansAgentDetailView', [
            'loan' => $loan,
            'account' => $this->accountInfo($loan->customer),
            'missingDocuments' => $loan->missingDocuments(),
            'validator' => $loan->validated_by ? User::find($loan->validated_by)?->only('id', 'name') : null,
            'disbursement' => [
                'methods' => Loan::DISBURSEMENT_METHODS,
                'opening_fee' => round((float) $loan->amount * $product->opening_fee / 100, 2),
                'opening_fee_iva' => round((float) $loan->amount * $product->opening_fee / 100 * $product->iva / 100, 2),
            ],
            'pldResults' => Loan::PLD_RESULTS,
            'can' => [
                'validate' => $user->hasAnyRole(User::VALIDATOR_ROLES),
                'approve' => $user->hasAnyRole(User::APPROVER_ROLES) && $loan->validated_by !== $user->id,
                'reject' => $user->hasAnyRole([...User::VALIDATOR_ROLES, ...User::APPROVER_ROLES]),
                'disburse' => $user->hasAnyRole(User::APPROVER_ROLES),
            ],
        ]);
    }

    /** Cuenta de acceso del cliente: solo el correo; la contraseña nunca se muestra ni se guarda de forma recuperable. */
    private function accountInfo($customer): ?array
    {
        if (! $customer?->user_id) {
            return null;
        }

        return ['email' => optional(User::find($customer->user_id))->email ?? $customer->email];
    }

    public function inviteAccount($id, CreateCustomerAccountAction $createAccount)
    {
        $loan = Loan::with('customer')->findOrFail($id);

        if (! $loan->approved_at || ! $loan->customer?->email) {
            return new JsonResponse(['message' => 'La solicitud debe estar aprobada y el cliente tener correo'], 422);
        }

        try {
            $createAccount->invite($loan->customer);
        } catch (\Throwable $e) {
            report($e);

            return new JsonResponse(['message' => 'No se pudo enviar el correo de invitación'], 500);
        }
        AuditLogger::record('account.invited', $loan->customer, $loan->id);

        return new JsonResponse(['message' => "Se envió la invitación a {$loan->customer->email}"]);
    }

    public function update(Request $request, $id, CreateCustomerAccountAction $createAccount, GenerateLoanDocumentsAction $generateDocuments, CreatePaymentPlanAction $createPlan)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        $loan = Loan::with('customer')->findOrFail($id);
        $user = $request->user();

        if ($loan->approved_at != null || $loan->reject_at != null) {
            return new JsonResponse(['message' => 'La solicitud ya fue procesada'], 400);
        }

        if ($request->status == 'rejected') {
            if (! $user->hasAnyRole([...User::VALIDATOR_ROLES, ...User::APPROVER_ROLES])) {
                return new JsonResponse(['message' => 'Tu rol no puede rechazar solicitudes.'], 403);
            }
            $loan->update(['reject_at' => now(), 'reject_reason' => $request->reason]);
            AuditLogger::record('loan.rejected', $loan, $loan->id, note: $request->reason);

            return new JsonResponse(['loan' => $loan, 'message' => 'La solicitud fue rechazada']);
        }

        // Doble firma: el analista valida (Buró y PLD) y otra persona con rol de aprobación autoriza
        if (! $user->hasAnyRole(User::APPROVER_ROLES)) {
            return new JsonResponse(['message' => 'Solo un Gerente o un Administrador puede aprobar solicitudes.'], 403);
        }
        if (! $loan->validated_at) {
            return new JsonResponse(['message' => 'La solicitud debe estar validada (Buró de Crédito y cotejo PLD) antes de aprobarse.'], 422);
        }
        if ((int) $loan->validated_by === (int) $user->id) {
            return new JsonResponse(['message' => 'Quien validó la solicitud no puede aprobarla; debe hacerlo otra persona.'], 403);
        }
        if ($loan->pld_result !== 'sin_coincidencias') {
            return new JsonResponse(['message' => 'El cotejo PLD tiene coincidencias en listas: la solicitud no se puede aprobar.'], 422);
        }
        if ($missing = $loan->missingDocuments()) {
            return new JsonResponse(['message' => 'Faltan documentos obligatorios en el expediente: '.implode(', ', $missing).'.', 'missing' => $missing], 422);
        }

        // Condiciones aprobadas: por defecto las solicitadas; el equipo puede aprobar otro monto o plazo dentro de los límites del producto
        $product = LoanProduct::forType($loan->type);
        $request->validate([
            'amount' => "nullable|numeric|min:{$product->min_amount}|max:{$product->max_amount}",
            'term' => "nullable|integer|min:{$product->min_term}|max:{$product->max_term}",
        ], [
            'amount.min' => 'El monto mínimo del producto es de $'.number_format($product->min_amount).'.',
            'amount.max' => 'El monto máximo del producto es de $'.number_format($product->max_amount).'.',
            'term.min' => "El plazo mínimo del producto es de {$product->min_term} meses.",
            'term.max' => "El plazo máximo del producto es de {$product->max_term} meses.",
        ]);

        if (! $loan->customer?->email) {
            return new JsonResponse(['message' => 'El cliente no tiene correo registrado para crear su cuenta'], 422);
        }

        $before = ['amount' => (float) $loan->amount, 'term' => (int) $loan->term];
        $adjustments = [];
        if ($request->filled('amount') && round((float) $request->amount, 2) !== round((float) $loan->amount, 2)) {
            $adjustments['amount'] = $request->amount;
        }
        if ($request->filled('term') && (int) $request->term !== (int) $loan->term) {
            $adjustments['term'] = (int) $request->term;
        }

        try {
            DB::transaction(function () use ($loan, $createAccount, $generateDocuments, $createPlan, $adjustments, $user) {
                $loan->update([
                    'approved_at' => now(),
                    'start_at' => Carbon::now()->addMonth()->firstOfMonth(),
                    'approved_by' => $user->id,
                    // Lo que pidió el cliente se conserva aunque se apruebe otra cosa
                    'requested_amount' => $loan->requested_amount ?? $loan->amount,
                    'requested_term' => $loan->requested_term ?? $loan->term,
                ] + $adjustments);

                // 1. Cuenta del cliente, 2. Plan de pagos con las condiciones del producto, 3. Contratos y anexos
                $createAccount->handle($loan->customer);
                $createPlan->handle($loan);
                $generateDocuments->handle($loan->fresh(['customer', 'payment']));
            });

            $after = ['amount' => (float) $loan->fresh()->amount, 'term' => (int) $loan->fresh()->term];
            AuditLogger::record('loan.approved', $loan, $loan->id, after: $after);
            if ($adjustments) {
                AuditLogger::record('loan.conditions_changed', $loan, $loan->id, before: $before, after: $after);
            }
            AuditLogger::record('loan.plan_saved', $loan->fresh('payment')->payment, $loan->id, after: $after);

            // La invitación va fuera de la transacción: si el correo falla, la aprobación no se pierde
            try {
                $createAccount->invite($loan->customer);
            } catch (\Throwable $e) {
                report($e);
            }
        } catch (\Throwable $e) {
            report($e);

            return new JsonResponse(['message' => 'No se pudo aprobar la solicitud: ' . $e->getMessage()], 500);
        }

        return new JsonResponse(['loan' => $loan->fresh(), 'message' => 'La solicitud fue aprobada; se generó su plan de pagos, la cuenta del cliente y su documentación']);
    }

    public function planFunerario($id, MorehContractService $moreh)
    {
        $loan = Loan::findOrFail($id);

        if ($loan->type !== 'FUNERARIO' || ! $loan->policy_folio) {
            return new JsonResponse(['message' => 'Este préstamo no tiene un Plan Funerario como garantía'], 422);
        }

        $result = $moreh->findByFolio($loan->policy_folio);

        return new JsonResponse($result['body'], $result['status']);
    }

    public function payments(Request $request, LoanPortfolio $portfolio, $id)
    {
        $loan = Loan::with('payment.details', 'customer')->findOrFail($id);
        $user = $request->user();

        return Inertia::render('Payments/PaymentsView', [
            'loan' => $loan,
            'summary' => $portfolio->summary($loan),
            'movements' => $loan->movements()->latest('id')->get(),
            'kinds' => \App\Models\LoanPayment::KINDS,
            'product' => $loan->product()->only(['annual_rate', 'opening_fee', 'iva', 'moratory_rate', 'grace_days', 'early_payoff_penalty', 'min_forced_months']),
            'isAdmin' => $user->hasRole('Administrator'),
            'canGeneratePlan' => $user->hasAnyRole(User::APPROVER_ROLES),
            'message' => session('message'),
        ]);
    }

    /**
     * Plan de pagos con las condiciones aprobadas y las del producto; ya no se capturan tasa, comisión ni plazo a mano.
     * Sin willSave solo devuelve la tabla calculada; con willSave la guarda (y actualiza la documentación).
     */
    public function createpayments(Request $request, $id, CreatePaymentPlanAction $createPlan, GenerateLoanDocumentsAction $generateDocuments)
    {
        $loan = Loan::with('payment.details')->findOrFail($id);

        if (! $request->user()->hasAnyRole(User::APPROVER_ROLES)) {
            return new JsonResponse(['message' => 'Solo un Gerente o un Administrador puede generar el plan de pagos.'], 403);
        }
        if (! $loan->approved_at) {
            return new JsonResponse(['message' => 'El préstamo debe estar aprobado para generar su plan de pagos.'], 422);
        }

        if (! filter_var($request->willSave, FILTER_VALIDATE_BOOLEAN)) {
            return new JsonResponse(['result' => $createPlan->preview($loan), 'message' => 'Se ha generado correctamente la tabla de pagos']);
        }

        try {
            $payment = $createPlan->handle($loan);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        AuditLogger::record('loan.plan_saved', $payment, $loan->id, after: ['amount' => (float) $loan->amount, 'months' => $payment->details->count()]);

        $message = 'Se ha guardado correctamente la tabla de pagos';
        try {
            $generateDocuments->handle($loan->fresh(['customer', 'payment']));
            $message .= ' y se actualizó la documentación del préstamo';
        } catch (\Throwable $e) {
            report($e);
            $message .= ', pero no se pudo actualizar la documentación';
        }

        return new JsonResponse(['result' => $createPlan->preview($loan), 'message' => $message, 'payment' => $payment]);
    }

    public function history()
    {
        $loans = Loan::with('customer')
            ->whereNotNull('paid_at')
            ->orderBy('id', 'desc')
            ->paginate();

        return Inertia::render('Loans/LoansHistoryView', ['loans' => $loans]);
    }

    public function documents(Request $request, $id)
    {
        $loan = Loan::findOrFail($id);

        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'documentsname' => 'required|array',
            'documentsname.*' => 'required|string|max:255',
            'documentstype' => 'required|array',
            'documentstype.*' => 'required|string|max:255|not_in:null,undefined',
        ], [
            'documents.required' => 'Agrega al menos un documento.',
            'documents.*.required' => 'Falta seleccionar el archivo del documento #:position.',
            'documents.*.uploaded' => 'El documento #:position no se pudo subir; revisa que no pese más de 5 MB.',
            'documents.*.file' => 'El documento #:position no se pudo subir; revisa que no pese más de 5 MB.',
            'documents.*.mimes' => 'El documento #:position debe ser PDF, DOC, DOCX, JPG o PNG.',
            'documents.*.max' => 'El documento #:position pesa más de 5 MB.',
            'documentsname.*.required' => 'Escribe el nombre del documento #:position.',
            'documentstype.*.required' => 'Selecciona el tipo del documento #:position.',
            'documentstype.*.not_in' => 'Selecciona el tipo del documento #:position.',
        ]);

        $uploadedDocuments = [];

        $documentsName = $request->documentsname;
        $documentsType = $request->documentstype;

        foreach ($request->documents as $index => $document) {
            $name = $document->getClientOriginalName();
            $path = (new UploadFileAction)->execute($document, "loans/{$loan->id}/uploads", $name);

            $document = $loan->documentations()->create([
                'name' => $documentsName[$index],
                'type' => $documentsType[$index],
                'path' => $path,
                'uploaded_by' => $request->user()->id,
            ]);
            AuditLogger::record('document.uploaded', $document, $loan->id, note: $documentsName[$index]);

            $uploadedDocuments[] = $document;
        }


        return new JsonResponse([
            'message' => 'Se han actualizado los documentos correctamente',
            'documents' => $uploadedDocuments,
            'missingDocuments' => $loan->missingDocuments(),
        ]);
    }

    public function destroyDocument($id, $documentId)
    {
        $loan = Loan::findOrFail($id);
        $document = $loan->documentations()->findOrFail($documentId);

        // Con el crédito aprobado el expediente es parte del contrato: solo se agregan versiones nuevas
        if ($loan->approved_at) {
            return new JsonResponse(['message' => 'El préstamo ya fue aprobado: sus documentos no se pueden eliminar. Sube una versión nueva si necesitas reemplazarlo.'], 422);
        }

        Storage::disk(config('filesystems.documents'))->delete($document->path);
        $document->delete();
        AuditLogger::record('document.deleted', $document, $loan->id, note: $document->name);

        return new JsonResponse(['message' => 'Documento eliminado correctamente', 'missingDocuments' => $loan->missingDocuments()]);
    }

    /** Abre un documento del préstamo; en Firebase el bucket es privado, así que se firma un enlace temporal. */
    public function showDocument($id, $documentId)
    {
        $document = Loan::findOrFail($id)->documentations()->findOrFail($documentId);
        $disk = Storage::disk(config('filesystems.documents'));

        abort_unless($disk->exists($document->path), 404, 'El documento no se encuentra en el almacenamiento.');

        if (config('filesystems.documents') !== 'firebase') {
            return $disk->response($document->path);
        }

        return redirect($disk->temporaryUrl($document->path, now()->addMinutes(15)));
    }
}
