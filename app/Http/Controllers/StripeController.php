<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStripeSessionPaymentRequest;
use App\Models\PaymentDetail;
use App\Services\LoanLedger;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    public function createCheckoutSession(CreateStripeSessionPaymentRequest $request)
    {
        $paymentDetail = PaymentDetail::with('payment.loan.customer')->findOrFail($request->payment_id);

        $user = $request->user();
        $isStaff = $user->isStaff();
        if (! $isStaff && $paymentDetail->payment?->loan?->customer?->user_id !== $user->id) {
            abort(403, 'Esta cuota no pertenece a tu cuenta.');
        }

        if ($paymentDetail->status === 'S') {
            return response()->json(['message' => 'Esta cuota ya está pagada.'], 422);
        }

        // Los pagos se aplican a lo más antiguo primero (prelación del contrato), así que se cobra la cuota pendiente más antigua
        $loan = $paymentDetail->payment->loan->load('payment.details');
        $oldest = $loan->payment->details->where('status', '!=', 'S')->sortBy('number')->first();
        if ($oldest->id !== $paymentDetail->id) {
            return response()->json(['message' => "Primero paga la cuota {$oldest->number}."], 422);
        }
        $amount = app(LoanLedger::class)->amountToCover($loan, $paymentDetail, now());

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => "Pago de la cuota {$paymentDetail->number} ({$paymentDetail->month}/{$paymentDetail->year})",
                    ],
                    'unit_amount' => (int) round($amount * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'client_reference_id' => (string) $paymentDetail->id,
            'metadata' => ['payment_detail_id' => (string) $paymentDetail->id, 'expected_cents' => (string) (int) round($amount * 100)],
            'success_url' => url('/success'),
            'cancel_url' => url('/cancel'),
        ]);

        // El navegador solo abre la URL de pago de Stripe; así no depende de ninguna llave pública (pk_)
        return response()->json(['id' => $session->id, 'url' => $session->url]);
    }

    /**
     * Stripe regresa aquí al cliente, pero esta URL no confirma pagos: eso lo hace
     * únicamente el webhook firmado (StripeWebhookController).
     */
    public function success(Request $request)
    {
        return redirect()->route('dashboard')->with('message', 'Recibimos tu pago. En unos segundos se verá reflejado en tu cuenta.');
    }

    public function cancel(Request $request)
    {
        return redirect()->route('dashboard')->with('message', 'El pago fue cancelado. No se realizó ningún cargo.');
    }
}
