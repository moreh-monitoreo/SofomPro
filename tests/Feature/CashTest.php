<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashCut;
use App\Models\LoanPayment;
use App\Services\PaymentRegistrar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class CashTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
    }

    /** Tres pagos el 21 de octubre (efectivo, transferencia y Stripe), uno revertido y uno de otro día. */
    private function day(): array
    {
        $registrar = app(PaymentRegistrar::class);
        $advisor = $this->userWithRole('Advisor');
        ['loan' => $a] = $this->approvedLoan();
        ['loan' => $b] = $this->approvedLoan();
        ['loan' => $c] = $this->approvedLoan();

        $cash = $registrar->apply($a, 500, 'EFECTIVO', null, now(), $advisor->id);
        $transfer = $registrar->apply($b, 300, 'TRANSFERENCIA', 'SPEI-1', now(), $advisor->id);
        $stripe = $registrar->apply($c, 200, 'STRIPE', 'pi_1', now());
        $stripe->forceFill(['processor_fee' => 9.5, 'processor_net' => 190.5])->save();

        $reverted = $registrar->apply($a, 100, 'EFECTIVO', null, now(), $advisor->id);
        $registrar->revert($reverted);
        $registrar->apply($b, 50, 'EFECTIVO', null, now()->subDay(), $advisor->id);

        return compact('cash', 'transfer', 'stripe', 'advisor');
    }

    public function test_only_managers_and_administrators_see_the_cash_report(): void
    {
        $this->actingAs($this->userWithRole('Advisor'))->get('/cash')->assertForbidden();
        $this->actingAs($this->userWithRole('Analista de crédito'))->get('/cash')->assertForbidden();
        $this->actingAs($this->userWithRole('Gerente'))->get('/cash')->assertOk();
    }

    public function test_the_report_totals_by_method_and_person_without_reverted_payments(): void
    {
        $this->day();

        $this->actingAs($this->userWithRole('Gerente'))->get('/cash?from=2026-10-21&to=2026-10-21')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Cash/Index')
                ->has('movements', 3)
                ->where('totals.total', 1000)
                ->where('totals.by_method.EFECTIVO.amount', 500)
                ->where('totals.by_method.TRANSFERENCIA.amount', 300)
                ->where('totals.by_method.STRIPE.amount', 200)
                ->where('totals.by_method.STRIPE.fees', 9.5)
                ->has('byUser', 2)
                ->where('canClose', true)
                ->where('pendingReconciliation', 2));
    }

    public function test_closing_the_day_compares_the_cash_and_cannot_be_changed(): void
    {
        $this->day();
        $manager = $this->userWithRole('Gerente');

        $this->actingAs($manager)->postJson('/cash/close', ['date' => '2026-10-22', 'cash_counted' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('date');
        $this->actingAs($manager)->postJson('/cash/close', ['date' => '2026-10-21'])
            ->assertStatus(422)->assertJsonValidationErrors('cash_counted');

        $this->actingAs($manager)->postJson('/cash/close', ['date' => '2026-10-21', 'cash_counted' => 480, 'notes' => 'Faltan 20 de cambio'])
            ->assertOk()->assertJsonFragment(['message' => 'Corte cerrado con una diferencia de $-20.00 en efectivo.']);

        $cut = CashCut::firstOrFail();
        $this->assertSame(1000.0, $cut->total);
        $this->assertSame(500.0, $cut->cash_expected);
        $this->assertSame(-20.0, $cut->cash_difference);
        $this->assertSame($manager->id, $cut->closed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cash.closed', 'user_id' => $manager->id]);

        $this->actingAs($manager)->postJson('/cash/close', ['date' => '2026-10-21', 'cash_counted' => 500])->assertStatus(409);

        $this->expectException(\LogicException::class);
        $cut->update(['cash_counted' => 500]);
    }

    public function test_a_payment_registered_after_the_cut_is_flagged(): void
    {
        ['loan' => $loan] = $this->approvedLoan();
        $manager = $this->userWithRole('Gerente');
        $this->actingAs($manager)->postJson('/cash/close', ['date' => '2026-10-21', 'cash_counted' => 0])->assertOk();

        $this->travel(1)->hours();
        app(PaymentRegistrar::class)->apply($loan, 100, 'EFECTIVO', null, Carbon::parse('2026-10-21 09:00'), $manager->id);

        $this->actingAs($manager)->get('/cash?from=2026-10-21&to=2026-10-21')
            ->assertInertia(fn ($page) => $page->where('movements.0.after_cut', true)->where('cut.total', 0));
    }

    public function test_bank_payments_are_reconciled_with_a_reference(): void
    {
        ['cash' => $cash, 'transfer' => $transfer, 'stripe' => $stripe] = $this->day();
        $manager = $this->userWithRole('Gerente');

        // El efectivo no se concilia contra el banco
        $this->actingAs($manager)->postJson('/cash/reconcile', ['ids' => [$cash->id], 'bank_reference' => 'X'])->assertStatus(422);
        $this->actingAs($manager)->postJson('/cash/reconcile', ['ids' => [$transfer->id]])->assertStatus(422)->assertJsonValidationErrors('bank_reference');

        $this->actingAs($manager)->postJson('/cash/reconcile', ['ids' => [$transfer->id, $stripe->id], 'bank_reference' => 'EDO-CTA-21OCT'])
            ->assertOk()->assertJsonPath('data.net', 490.5);

        $this->assertSame('EDO-CTA-21OCT', $transfer->fresh()->bank_reference);
        $this->assertNotNull($stripe->fresh()->reconciled_at);
        $this->assertSame(2, AuditLog::where('action', 'payment.reconciled')->count());

        // Ya conciliado: no se concilia dos veces ni se puede revertir
        $this->actingAs($manager)->postJson('/cash/reconcile', ['ids' => [$transfer->id], 'bank_reference' => 'otra'])->assertStatus(422);
        $this->expectExceptionMessage('ya está conciliado');
        app(PaymentRegistrar::class)->revert($transfer->fresh());
    }

    public function test_the_export_lists_the_payments(): void
    {
        $this->day();

        $csv = $this->actingAs($this->userWithRole('Gerente'))->get('/cash/export?from=2026-10-21&to=2026-10-21')
            ->assertOk()->streamedContent();

        $this->assertSame(4, substr_count(trim($csv), "\n") + 1);
        $this->assertStringContainsString('Tarjeta (Stripe)', $csv);
        $this->assertStringContainsString('SPEI-1', $csv);
    }
}
