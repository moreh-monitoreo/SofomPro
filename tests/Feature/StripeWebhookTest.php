<?php

namespace Tests\Feature;

use App\Models\PaymentDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    /** Al 21/oct/2026 la primera cuota (1,160) vence desde el 5/sep: 46 días de moratorios con IVA (72.21). */
    private const DUE_CENTS = 123221;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => self::SECRET]);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00'));
    }

    private function signed(array $event, string $secret = self::SECRET): array
    {
        $payload = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return [$payload, ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json']];
    }

    private function paidEvent(PaymentDetail $detail, array $override = []): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => array_merge([
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'amount_total' => self::DUE_CENTS,
                'currency' => 'mxn',
                'payment_intent' => 'pi_test_123',
                'metadata' => ['payment_detail_id' => (string) $detail->id, 'expected_cents' => (string) self::DUE_CENTS],
            ], $override)],
        ];
    }

    private function sendWebhook(string $payload, array $server)
    {
        return $this->call('POST', '/stripe/webhook', [], [], [], $server, $payload);
    }

    public function test_a_signed_paid_event_marks_the_installment_as_paid(): void
    {
        $detail = $this->approvedLoan()['details']->first();
        [$payload, $server] = $this->signed($this->paidEvent($detail));

        $this->sendWebhook($payload, $server)->assertOk();

        $detail->refresh();
        $this->assertSame('S', $detail->status);
        $this->assertSame('STRIPE', $detail->payment_method);
        $this->assertSame('pi_test_123', $detail->payment_reference);
        $this->assertNotNull($detail->payed_at);
    }

    public function test_replaying_the_same_event_does_not_change_the_payment(): void
    {
        $detail = $this->approvedLoan()['details']->first();
        [$payload, $server] = $this->signed($this->paidEvent($detail));

        $this->sendWebhook($payload, $server)->assertOk();
        $firstPaidAt = $detail->fresh()->payed_at;
        $this->travel(5)->minutes();
        $this->sendWebhook($payload, $server)->assertOk();

        $this->assertEquals($firstPaidAt, $detail->fresh()->payed_at);
    }

    public function test_an_event_with_a_forged_signature_is_rejected(): void
    {
        $detail = $this->approvedLoan()['details']->first();
        [$payload, $server] = $this->signed($this->paidEvent($detail), 'whsec_another_secret');

        $this->sendWebhook($payload, $server)->assertStatus(400);
        $this->assertSame('N', $detail->fresh()->status);
    }

    public function test_an_event_without_signature_is_rejected(): void
    {
        $detail = $this->approvedLoan()['details']->first();

        $this->sendWebhook(json_encode($this->paidEvent($detail)), ['CONTENT_TYPE' => 'application/json'])->assertStatus(400);
        $this->assertSame('N', $detail->fresh()->status);
    }

    public function test_the_webhook_is_refused_when_no_secret_is_configured(): void
    {
        config(['services.stripe.webhook_secret' => null]);
        $detail = $this->approvedLoan()['details']->first();
        [$payload, $server] = $this->signed($this->paidEvent($detail));

        $this->sendWebhook($payload, $server)->assertStatus(503);
        $this->assertSame('N', $detail->fresh()->status);
    }

    public function test_an_amount_that_does_not_match_the_installment_is_not_accepted(): void
    {
        $detail = $this->approvedLoan()['details']->first();
        [$payload, $server] = $this->signed($this->paidEvent($detail, ['amount_total' => 100]));

        $this->sendWebhook($payload, $server)->assertOk();
        $this->assertSame('N', $detail->fresh()->status);
    }

    public function test_an_unpaid_session_does_not_mark_the_installment_as_paid(): void
    {
        $detail = $this->approvedLoan()['details']->first();
        [$payload, $server] = $this->signed($this->paidEvent($detail, ['payment_status' => 'unpaid']));

        $this->sendWebhook($payload, $server)->assertOk();
        $this->assertSame('N', $detail->fresh()->status);
    }

    public function test_visiting_the_success_url_no_longer_marks_anything_as_paid(): void
    {
        ['details' => $details, 'owner' => $owner] = $this->approvedLoan();
        $detail = $details->first();

        $this->get("/success?payment_detail_id={$detail->id}")->assertRedirect(route('dashboard'));
        $this->actingAs($owner)->get("/success?payment_detail_id={$detail->id}")->assertRedirect(route('dashboard'));

        $this->assertSame('N', $detail->fresh()->status);
    }

    public function test_a_customer_cannot_start_checkout_for_someone_elses_installment(): void
    {
        $detail = $this->approvedLoan()['details']->first();
        $stranger = $this->userWithRole('Customer');

        $this->actingAs($stranger)->postJson('/create-checkout-session', ['payment_id' => $detail->id])->assertForbidden();
    }

    public function test_checkout_charges_the_oldest_installment_with_its_moratory_interest(): void
    {
        ['details' => $details, 'owner' => $owner] = $this->approvedLoan();

        $this->actingAs($owner)->postJson('/create-checkout-session', ['payment_id' => $details[1]->id])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Primero paga la cuota 1.']);
    }

    public function test_an_already_paid_installment_cannot_be_charged_again(): void
    {
        ['details' => $details, 'owner' => $owner] = $this->approvedLoan();
        $detail = $details->first();
        $detail->forceFill(['status' => 'S'])->save();

        $this->actingAs($owner)->postJson('/create-checkout-session', ['payment_id' => $detail->id])->assertStatus(422);
    }
}
