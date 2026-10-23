<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/** Recibos de pago en PDF: los puede descargar el dueño del préstamo o el equipo. */
class ReceiptController extends Controller
{
    public const METHOD_LABELS = [
        'STRIPE' => 'Tarjeta (Stripe)',
        'TRANSFERENCIA' => 'Transferencia bancaria',
        'DEPOSITO' => 'Depósito bancario',
        'EFECTIVO' => 'Efectivo',
        'OTRO' => 'Otro',
    ];

    public function download(int $loanId, int $detailId)
    {
        $loan = Loan::with(['customer', 'payment.details'])->findOrFail($loanId);
        $detail = $loan->payment?->details->firstWhere('id', $detailId);
        abort_unless($detail, 404);

        $user = auth()->user();
        $isStaff = $user->hasAnyRole(['Administrator', 'Advisor']);
        abort_unless($isStaff || $loan->customer->user_id === $user->id, 403);
        abort_unless($detail->status === 'S', 404, 'Esta cuota todavía no está pagada.');

        $installments = $loan->payment->details->sortBy('number');
        $paidTotal = $installments->where('status', 'S')->sum(fn ($d) => (float) $d->monthly_payment);
        $vat = round((float) $detail->monthly_payment - (float) $detail->principal_payment - (float) $detail->interest_payment, 2);

        $number = sprintf('REC-%06d-%02d', $loan->id, $detail->number);

        $pdf = Pdf::loadView('pdf.receipt', [
            'company' => config('promas'),
            'number' => $number,
            'issuedAt' => now(),
            'loan' => $loan,
            'folio' => $loan->folio ?: sprintf('PRM-%06d', $loan->id),
            'customer' => $loan->customer,
            'detail' => $detail,
            'installments' => $installments->count(),
            'vat' => $vat,
            'method' => self::METHOD_LABELS[$detail->payment_method] ?? Str::title((string) $detail->payment_method),
            'paidTotal' => round($paidTotal, 2),
            'pendingTotal' => round($installments->where('status', '!=', 'S')->sum(fn ($d) => (float) $d->monthly_payment), 2),
        ])->setPaper('letter');

        return $pdf->download("{$number}.pdf");
    }
}
