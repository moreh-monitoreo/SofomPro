<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\JsonResponse;
use App\Models\LoanPayment;
use App\Models\PaymentDetail;
use App\Services\AuditLogger;
use App\Services\LoanPortfolio;
use App\Services\PaymentRegistrar;
use InvalidArgumentException;

/** Correcciones de cartera: solo el Administrador puede deshacer un pago manual (el más reciente del préstamo). */
class PaymentCorrectionController extends Controller
{
    public function revert(int $detailId, PaymentRegistrar $registrar, LoanPortfolio $portfolio)
    {
        $detail = PaymentDetail::findOrFail($detailId);

        try {
            $registrar->revertDetail($detail, auth()->id());
        } catch (InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }

        $loan = $detail->payment->loan->load('payment.details');
        AuditLogger::record('payment.reverted', $detail, $loan->id, after: ['number' => $detail->number]);

        return JsonResponse::success([
            'detail' => $detail->fresh(),
            'details' => $loan->payment->details->sortBy('number')->values(),
            'summary' => $portfolio->summary($loan),
        ], 'El pago fue revertido.');
    }

    /** Revierte un pago completo (abono, pago anticipado o liquidación) desde el historial de pagos. */
    public function revertMovement(int $movementId, PaymentRegistrar $registrar, LoanPortfolio $portfolio)
    {
        $movement = LoanPayment::findOrFail($movementId);

        try {
            $registrar->revert($movement, auth()->id());
        } catch (InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }

        $loan = $movement->loan->load('payment.details');
        AuditLogger::record('payment.reverted', $movement, $loan->id, after: ['kind' => $movement->kind, 'amount' => $movement->amount]);

        return JsonResponse::success([
            'details' => $loan->payment->details->sortBy('number')->values(),
            'movement' => $movement->fresh(),
            'summary' => $portfolio->summary($loan),
        ], 'El pago fue revertido.');
    }
}
