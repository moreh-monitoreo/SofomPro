<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\JsonResponse;
use App\Models\PaymentDetail;
use App\Services\AuditLogger;
use App\Services\LoanPortfolio;
use App\Services\PaymentRegistrar;
use InvalidArgumentException;

/** Correcciones de cartera: solo el Administrador puede deshacer un pago manual. */
class PaymentCorrectionController extends Controller
{
    public function revert(int $detailId, PaymentRegistrar $registrar, LoanPortfolio $portfolio)
    {
        $detail = PaymentDetail::findOrFail($detailId);

        try {
            $registrar->revert($detail);
        } catch (InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }

        $loan = $detail->payment->loan->load('payment.details');
        AuditLogger::record('payment.reverted', $detail, $loan->id, after: ['number' => $detail->number]);

        return JsonResponse::success([
            'detail' => $detail->fresh(),
            'summary' => $portfolio->summary($loan),
        ], 'El pago fue revertido.');
    }
}
