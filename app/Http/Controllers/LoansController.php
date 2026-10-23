<?php

namespace App\Http\Controllers;

use App\Actions\CreateQuotationTableAction;
use App\Actions\Loans\CreateCustomerAccountAction;
use App\Actions\Loans\GenerateLoanDocumentsAction;
use App\Services\MorehContractService;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanPortfolio;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Actions\Global\Files\UploadFileAction;

class LoansController extends Controller
{
    public function index()
    {
        $loans = Loan::with('customer')->withCount('payment')->orderBy('id', 'desc')->paginate();
        
        return Inertia::render('Loans/LoansAgentView', [
            'loans' => $loans,
            'message' => session('message'),
        ]);
    }

    public function show($id)
    {
        $loan = Loan::with(['customer.addresses', 'customer.employment', 'documentations'])->findOrFail($id);

        return Inertia::render('Loans/LoansAgentDetailView', [
            'loan' => $loan,
            'account' => $this->accountInfo($loan->customer),
        ]);
    }

    /** Cuenta de acceso del cliente (solo para administrador/asesor). */
    private function accountInfo($customer): ?array
    {
        if (! $customer?->user_id) {
            return null;
        }

        return [
            'email' => optional(User::find($customer->user_id))->email ?? $customer->email,
            'password' => CreateCustomerAccountAction::readPassword($customer),
        ];
    }

    public function resetAccountPassword($id, CreateCustomerAccountAction $createAccount)
    {
        $loan = Loan::with('customer')->findOrFail($id);

        if (! $loan->approved_at || ! $loan->customer?->email) {
            return new JsonResponse(['message' => 'La solicitud debe estar aprobada y el cliente tener correo'], 422);
        }

        $password = $createAccount->resetPassword($loan->customer);

        return new JsonResponse([
            'message' => 'Se generó una nueva contraseña',
            'account' => ['email' => $loan->customer->email, 'password' => $password],
        ]);
    }

    public function update(Request $request, $id, CreateCustomerAccountAction $createAccount, GenerateLoanDocumentsAction $generateDocuments)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        $loan = Loan::with('customer')->findOrFail($id);

        if ($loan->approved_at != null || $loan->reject_at != null) {
            return new JsonResponse(['message' => 'La solicitud ya fue procesada'], 400);
        }

        if ($request->status == 'rejected') {
            $loan->update(['reject_at' => now(), 'reject_reason' => $request->reason]);

            return new JsonResponse(['loan' => $loan, 'message' => 'La solicitud fue rechazada']);
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

        $adjustments = [];
        if ($request->filled('amount') && round((float) $request->amount, 2) !== round((float) $loan->amount, 2)) {
            $adjustments += ['requested_amount' => $loan->amount, 'amount' => $request->amount];
        }
        if ($request->filled('term') && (int) $request->term !== (int) $loan->term) {
            $adjustments += ['requested_term' => $loan->term, 'term' => (int) $request->term];
        }

        try {
            DB::transaction(function () use ($loan, $createAccount, $generateDocuments, $adjustments, $request) {
                $loan->update([
                    'approved_at' => now(),
                    'start_at' => Carbon::now()->addMonth()->firstOfMonth(),
                    'approved_by' => $request->user()->id,
                ] + $adjustments);

                // 1. Cuenta del cliente, 2. Contratos y anexos según el tipo de préstamo
                $createAccount->handle($loan->customer);
                $generateDocuments->handle($loan);
            });
        } catch (\Throwable $e) {
            report($e);

            return new JsonResponse(['message' => 'No se pudo aprobar la solicitud: ' . $e->getMessage()], 500);
        }

        return new JsonResponse(['loan' => $loan->fresh(), 'message' => 'La solicitud fue aprobada, se creó la cuenta del cliente y su documentación']);
    }

    public function createpayments(Request $request, $id, CreateQuotationTableAction $createQuotationTableAction, GenerateLoanDocumentsAction $generateDocuments)
    {
        $loan = Loan::with('payment.details')->findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'months' => 'required_without:years|nullable|integer|min:1|max:360',
            'years' => 'required_without:months|nullable|numeric|min:0.01',
            'opening_fees' => 'required|numeric|min:0|max:100',
            'annual_rate' => 'required|numeric|min:0|max:100',
        ]);

        $startDate = new Carbon($request->start_date ?? null);
        $years = $request->filled('months') ? $request->months / 12 : $request->years;

        $paymentTable = $createQuotationTableAction->handle($request->amount, $years, $request->opening_fees, $request->annual_rate, LoanProduct::forType($loan->type)->iva / 100);

        // "Generar plan" solo calcula; "Guardar plan" lo persiste
        if (! filter_var($request->willSave, FILTER_VALIDATE_BOOLEAN)) {
            return new JsonResponse([
                'result' => $paymentTable,
                'message' => 'Se ha generado correctamente la tabla de pagos'
            ]);
        }

        // Guardar de nuevo reemplaza el plan anterior, salvo que ya tenga pagos registrados
        if ($loan->payment) {
            if ($loan->payment->details->where('status', '!=', 'N')->isNotEmpty()) {
                return new JsonResponse(['message' => 'El plan ya tiene pagos registrados y no se puede reemplazar'], 422);
            }
            $loan->payment->details()->delete();
            $loan->payment->delete();
        }

        $payment = Payment::create([
            'loan_id' => $loan->id,
            'request_amount' => $request->amount,
            'payment_amount' => $paymentTable['to_pay_simple_interest'],
            'cat_type' => 'irr',
            'cat_amount' => $paymentTable['cat_con_iva'] / 100,
            'cat_sin_iva' => $paymentTable['cat_sin_iva'] / 100,
            'total_months' => $paymentTable['total_months'],
            'month_rate' => $paymentTable['month_rate'],
            'opening_fees' => $paymentTable['opening_fees'],
            'status' => 'N',
        ]);

        foreach ($paymentTable['payments'] as $index => $pay) {
            $newDate = $startDate->copy()->addMonthsNoOverflow($index + 1);

            $payment->details->push(PaymentDetail::create([
                'payment_id' => $payment->id,
                'number' => $index + 1,
                'month' => $newDate->format('m'),
                'year' => $newDate->year,
                'due_date' => $newDate->toDateString(),
                'interest_payment' => $pay['interest_payment'],
                'principal_payment' => $pay['principal_payment'],
                'balance' => $pay['balance'],
                'monthly_payment' => $pay['monthly_payment'],
                'status' => 'N',
            ]));
        }

        // Si el préstamo ya está aprobado, la documentación (carátula y tabla de amortización) se actualiza con este plan
        $message = 'Se ha guardado correctamente la tabla de pagos';
        if ($loan->approved_at) {
            try {
                $generateDocuments->handle($loan->fresh(['customer', 'payment']));
                $message .= ' y se actualizó la documentación del préstamo';
            } catch (\Throwable $e) {
                report($e);
                $message .= ', pero no se pudo actualizar la documentación';
            }
        }

        return new JsonResponse([
            'result' => $paymentTable,
            'message' => $message,
            'payment' => $payment,
        ]);
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
            ]);

            $uploadedDocuments[] = $document;
        }


        return new JsonResponse([
            'message' => 'Se han actualizado los documentos correctamente',
            'documents' => $uploadedDocuments,
        ]);
    }

    public function destroyDocument($id, $documentId)
    {
        $document = Loan::findOrFail($id)->documentations()->findOrFail($documentId);

        Storage::disk(config('filesystems.documents'))->delete($document->path);
        $document->delete();

        return new JsonResponse(['message' => 'Documento eliminado correctamente']);
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
