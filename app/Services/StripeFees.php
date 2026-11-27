<?php

namespace App\Services;

use Stripe\StripeClient;

/**
 * Comisión que Stripe cobró por un pago y lo que deposita al banco (de la transacción de saldo del cargo).
 * Si Stripe no responde, devuelve null: el pago ya quedó aplicado y la comisión se puede ver en el panel de Stripe.
 */
class StripeFees
{
    /** @return array{fee: float, net: float}|null */
    public function forPaymentIntent(string $paymentIntentId): ?array
    {
        $secret = config('services.stripe.secret');
        if (blank($secret) || ! str_starts_with($paymentIntentId, 'pi_')) {
            return null;
        }

        try {
            $intent = (new StripeClient($secret))->paymentIntents->retrieve($paymentIntentId, ['expand' => ['latest_charge.balance_transaction']]);
            $transaction = $intent->latest_charge->balance_transaction ?? null;
            if (! $transaction || ! isset($transaction->fee, $transaction->net)) {
                return null;
            }

            return ['fee' => $transaction->fee / 100, 'net' => $transaction->net / 100];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
