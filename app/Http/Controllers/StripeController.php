<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStripeSessionPaymentRequest;
use App\Models\PaymentDetail;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    public function createCheckoutSession(CreateStripeSessionPaymentRequest $request)
    {
        $paymentDetail = PaymentDetail::with('payment.loan.customer')->findOrFail($request->payment_id);

        $user = $request->user();
        $isStaff = $user->hasAnyRole(['Administrator', 'Advisor']);
        if (! $isStaff && $paymentDetail->payment?->loan?->customer?->user_id !== $user->id) {
            abort(403, 'Esta cuota no pertenece a tu cuenta.');
        }

        if ($paymentDetail->status === 'S') {
            return response()->json(['message' => 'Esta cuota ya está pagada.'], 422);
        }

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => "Pago de la cuota {$paymentDetail->number} ({$paymentDetail->month}/{$paymentDetail->year})",
                    ],
                    'unit_amount' => (int) round($paymentDetail->monthly_payment * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'client_reference_id' => (string) $paymentDetail->id,
            'metadata' => ['payment_detail_id' => (string) $paymentDetail->id],
            'success_url' => url('/success'),
            'cancel_url' => url('/cancel'),
        ]);

        return response()->json(['id' => $session->id]);
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
