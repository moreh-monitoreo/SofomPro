<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStripeSessionPaymentRequest;
use App\Models\Log;
use App\Models\PaymentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as FacadesLog;

class StripeController extends Controller
{
    public function createCheckoutSession(CreateStripeSessionPaymentRequest $request)
    {
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $paymentDetail = PaymentDetail::findOrFail($request->payment_id);

        $month = $paymentDetail->month;
        $year = $paymentDetail->year;
        $amount = $paymentDetail->monthly_payment * 100;

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => "Pago del mes $month/$year",
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url("/success?payment_detail_id=$paymentDetail->id"),
            'cancel_url' => url('/cancel'),
        ]);

        return response()->json(['id' => $session->id]);
    }

    public function success(Request $request)
    {
        Log::create([
            'method' => request()->method(),
            'url' => request()->url(),
            'request' => json_encode($request->all()),
            'response' => json_encode([]),
            'status' => 'ok',
        ]);

        $paymentDetail = PaymentDetail::find($request->payment_detail_id);

        $paymentDetail->status = 'S';
        $paymentDetail->payed_at = now();
        $paymentDetail->payment_method = 'STRIPE';
        $paymentDetail->save();

        return redirect()->route('dashboard');
    }

    public function cancel(Request $request)
    {
        Log::create([
            'method' => request()->method(),
            'url' => request()->url(),
            'request' => json_encode($request->all()),
            'response' => json_encode([]),
            'status' => 'ok',
        ]);

        return redirect()->route('dashboard');
    }
}
