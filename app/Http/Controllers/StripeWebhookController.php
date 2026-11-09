<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\PaymentDetail;
use App\Services\PaymentRegistrar;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    private const PAID_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
    ];

    public function handle(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');
        if (blank($secret)) {
            report(new \RuntimeException('STRIPE_WEBHOOK_SECRET no está configurado; el webhook de Stripe se rechazó.'));

            return response()->json(['message' => 'Webhook no configurado'], 503);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            return response()->json(['message' => 'Firma inválida'], 400);
        }

        if (in_array($event->type, self::PAID_EVENTS, true)) {
            $this->markAsPaid($event->data->object, $event->id);
        }

        return response()->json(['received' => true]);
    }

    private function markAsPaid($session, string $eventId): void
    {
        if (($session->payment_status ?? null) !== 'paid') {
            return;
        }

        $paymentDetailId = $session->metadata->payment_detail_id ?? null;
        $detail = $paymentDetailId ? PaymentDetail::find($paymentDetailId) : null;

        if (! $detail) {
            $this->log($eventId, 'error', ['reason' => 'payment_detail no encontrado', 'session' => $session->id]);

            return;
        }

        // El importe lo fija el servidor al crear la sesión (cuota pendiente con moratorios a esa fecha)
        $expected = (int) ($session->metadata->expected_cents ?? round($detail->monthly_payment * 100));
        if ((int) $session->amount_total !== $expected || strtolower((string) $session->currency) !== 'mxn') {
            $this->log($eventId, 'error', [
                'reason' => 'el monto o la moneda no coinciden',
                'esperado' => $expected,
                'recibido' => $session->amount_total,
                'payment_detail_id' => $detail->id,
            ]);

            return;
        }

        $registered = app(PaymentRegistrar::class)->markPaid(
            $detail,
            'STRIPE',
            $session->payment_intent ?? $session->id,
            $session->amount_total / 100,
        );

        if ($registered) {
            $this->log($eventId, 'ok', ['payment_detail_id' => $detail->id, 'session' => $session->id]);
        }
    }

    private function log(string $eventId, string $status, array $data): void
    {
        Log::create([
            'method' => 'POST',
            'url' => 'stripe/webhook',
            'request' => json_encode(['event' => $eventId]),
            'response' => json_encode($data),
            'status' => $status,
        ]);
    }
}
