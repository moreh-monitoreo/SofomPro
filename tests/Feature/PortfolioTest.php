<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PaymentDetail;
use App\Services\LoanLedger;
use App\Services\LoanPortfolio;
use App\Services\PaymentRegistrar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
    }

    // ---- Saldo, próxima cuota y mora -------------------------------------------------------

    public function test_the_summary_reports_balance_next_installment_and_overdue(): void
    {
        ['loan' => $loan] = $this->approvedLoan();

        $s = app(LoanPortfolio::class)->summary($loan);

        $this->assertSame(3480.0, $s['total_to_pay']);
        $this->assertSame(0.0, $s['paid_amount']);
        $this->assertSame(3480.0, $s['pending_amount']);
        $this->assertSame(3000.0, $s['principal_balance']);
        $this->assertSame(1, $s['next_installment']['number']);
        $this->assertSame('2026-09-05', $s['next_installment']['due_date']);
        $this->assertTrue($s['is_overdue']);
        $this->assertSame(2, $s['overdue_installments']);
        $this->assertSame(2320.0, $s['overdue_amount']);
        $this->assertSame(46, $s['days_overdue']);
    }

    // Al 21/oct/2026: la cuota 1 (5/sep) lleva 46 días de atraso y la 2 (5/oct) 16. Moratorio = 1,160 × 42 % / 360 × días, más IVA.
    private const MORATORY_1 = 62.25 + 9.96;
    private const MORATORY_2 = 21.65 + 3.46;

    public function test_overdue_installments_accrue_moratory_interest_with_vat(): void
    {
        ['loan' => $loan, 'details' => $details] = $this->approvedLoan();
        $ledger = app(LoanLedger::class);
        $product = $loan->product();

        $c = $ledger->charges($details[0], $product, today());
        $this->assertSame(46, $c['days_late']);
        $this->assertSame(62.25, $c['moratory']);
        $this->assertSame(9.96, $c['moratory_iva']);
        $this->assertSame(1232.21, $c['total']);
        $this->assertSame(0.0, $ledger->charges($details[2], $product, today())['moratory']);

        $s = app(LoanPortfolio::class)->summary($loan);
        $this->assertSame(round(self::MORATORY_1 + self::MORATORY_2, 2), $s['moratory_amount']);
        $this->assertSame(2417.32, $s['amount_due_today']);
    }

    public function test_paying_the_overdue_installments_clears_the_arrears(): void
    {
        ['loan' => $loan, 'details' => $details] = $this->approvedLoan();
        $registrar = app(PaymentRegistrar::class);

        $registrar->markPaid($details[0], 'TRANSFERENCIA', 'REF1');
        $registrar->markPaid($details[1], 'EFECTIVO');

        $s = app(LoanPortfolio::class)->summary($loan->fresh());

        $this->assertFalse($s['is_overdue']);
        $this->assertSame(0, $s['days_overdue']);
        $this->assertSame(2417.32, $s['paid_amount']);
        $this->assertSame(1160.0, $s['pending_amount']);
        $this->assertSame(1000.0, $s['principal_balance']);
        $this->assertSame(0.0, $s['moratory_amount']);
        $this->assertSame(3, $s['next_installment']['number']);
        $this->assertSame(15, $s['next_installment']['days_until_due']);
    }

    public function test_the_grace_days_of_the_product_delay_the_overdue_flag_and_the_moratory_interest(): void
    {
        ['loan' => $loan] = $this->approvedLoan();
        LoanProduct::where('type', 'NORMAL')->update(['grace_days' => 60]);

        $s = app(LoanPortfolio::class)->summary($loan);
        $this->assertFalse($s['is_overdue']);
        $this->assertSame(0.0, $s['moratory_amount']);
        $this->assertFalse(Loan::overdue()->whereKey($loan->id)->exists());
    }

    public function test_installments_without_a_due_date_use_the_end_of_their_month(): void
    {
        ['details' => $details] = $this->approvedLoan();
        $legacy = $details[0];
        $legacy->forceFill(['due_date' => null, 'month' => '09', 'year' => 2026])->save();

        $this->assertSame('2026-09-30', $legacy->fresh()->dueDate()->toDateString());
        $this->assertTrue($legacy->fresh()->isOverdue());
        $this->assertTrue(PaymentDetail::overdue()->whereKey($legacy->id)->exists());

        $legacy->forceFill(['month' => '10', 'year' => 2026])->save();
        $this->assertFalse($legacy->fresh()->isOverdue());
        $this->assertFalse(PaymentDetail::overdue()->whereKey($legacy->id)->exists());
    }

    // ---- Registrador de pagos -------------------------------------------------------------

    public function test_a_stripe_payment_is_not_applied_twice(): void
    {
        ['details' => $details] = $this->approvedLoan();
        $registrar = app(PaymentRegistrar::class);

        $this->assertTrue($registrar->markPaid($details[0], 'STRIPE', 'pi_1', 1232.21));
        $this->assertFalse($registrar->markPaid($details[1], 'STRIPE', 'pi_1', 1185.11));
        $this->assertFalse($registrar->markPaid($details[0], 'EFECTIVO'));

        $d = $details[0]->fresh();
        $this->assertSame('S', $d->status);
        $this->assertSame('STRIPE', $d->payment_method);
        $this->assertSame('pi_1', $d->payment_reference);
        $this->assertSame('N', $details[1]->fresh()->status);
    }

    public function test_a_partial_payment_follows_the_order_of_the_contract(): void
    {
        ['loan' => $loan, 'details' => $details] = $this->approvedLoan();

        $movement = app(PaymentRegistrar::class)->apply($loan, 100, 'EFECTIVO', registeredBy: 7);

        // IVA de moratorios, moratorios y luego interés ordinario de la cuota más antigua
        $d = $details[0]->fresh();
        $this->assertSame('N', $d->status);
        $this->assertSame(9.96, $d->moratory_iva_paid);
        $this->assertSame(62.25, $d->moratory_paid);
        $this->assertSame(27.79, $d->interest_paid);
        $this->assertSame(0.0, $d->principal_paid);
        $this->assertEquals(100, $d->payment_total);
        $this->assertSame(27.79, $movement->allocations['details'][$d->id]['interest']);
        $this->assertSame(0.0, $details[1]->fresh()->interest_paid);
    }

    public function test_an_advance_payment_covers_the_overdue_and_then_the_next_installments(): void
    {
        ['loan' => $loan, 'details' => $details] = $this->approvedLoan();

        app(PaymentRegistrar::class)->apply($loan, 2417.32 + 500, 'TRANSFERENCIA', 'SPEI-1');

        $this->assertSame('S', $details[0]->fresh()->status);
        $this->assertSame('S', $details[1]->fresh()->status);
        $third = $details[2]->fresh();
        $this->assertSame('N', $third->status);
        $this->assertSame(160.0, $third->interest_paid);
        $this->assertSame(340.0, $third->principal_paid);
        $this->assertSame(660.0, app(LoanPortfolio::class)->summary($loan->fresh())['pending_amount']);
    }

    public function test_paying_more_than_is_owed_asks_for_an_early_payoff(): void
    {
        ['loan' => $loan] = $this->approvedLoan();

        $this->expectExceptionMessage('liquidación anticipada');
        app(PaymentRegistrar::class)->apply($loan, 9000, 'EFECTIVO');
    }

    public function test_a_prepayment_to_principal_recalculates_the_remaining_installments(): void
    {
        ['loan' => $loan, 'details' => $details, 'payment' => $payment] = $this->approvedLoan();
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00'));
        $registrar = app(PaymentRegistrar::class);

        $movement = $registrar->apply($loan, 1200, 'TRANSFERENCIA', 'SPEI-9', kind: 'anticipado');

        $fresh = $details->map->fresh();
        $this->assertEqualsWithDelta(1800, $fresh->sum(fn ($d) => (float) $d->principal_payment), 0.001);
        $this->assertSame(0.0, (float) $fresh->last()->balance);
        $this->assertSame(1200.0, (float) $payment->fresh()->prepaid_principal);
        $this->assertTrue($fresh->every(fn ($d) => (float) $d->monthly_payment < 1160));
        $this->assertSame(1800.0, app(LoanPortfolio::class)->summary($loan->fresh())['principal_balance']);

        // Revertir deja la tabla como estaba
        $registrar->revert($movement);
        $this->assertSame(1000.0, (float) $details[0]->fresh()->principal_payment);
        $this->assertSame(0.0, (float) $payment->fresh()->prepaid_principal);
    }

    public function test_a_prepayment_requires_being_current_and_at_least_one_installment(): void
    {
        ['loan' => $loan] = $this->approvedLoan();

        try {
            app(PaymentRegistrar::class)->apply($loan, 1500, 'EFECTIVO', kind: 'anticipado');
            $this->fail('Debió exigir estar al corriente.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('al corriente', $e->getMessage());
        }

        $this->travelTo(Carbon::parse('2026-09-01 12:00:00'));
        $this->expectExceptionMessage('al menos de una parcialidad');
        app(PaymentRegistrar::class)->apply($loan, 500, 'EFECTIVO', kind: 'anticipado');
    }

    public function test_the_payoff_covers_the_overdue_the_accrued_interest_and_the_principal(): void
    {
        ['loan' => $loan, 'details' => $details] = $this->approvedLoan();

        // Periodo de la cuota 3: del 5/oct al 5/nov (31 días); al 21/oct van 16 días de interés
        $payoff = app(LoanLedger::class)->payoff($loan, today());
        $this->assertSame(2417.32, $payoff['exigible']);
        $this->assertSame(82.58, $payoff['accrued_interest']);
        $this->assertSame(13.21, $payoff['accrued_iva']);
        $this->assertSame(1000.0, $payoff['principal']);
        $this->assertSame(0.0, $payoff['penalty']);
        $this->assertSame(3513.11, $payoff['total']);

        try {
            app(PaymentRegistrar::class)->apply($loan, 3000, 'EFECTIVO', kind: 'liquidacion');
            $this->fail('Debió exigir el monto exacto.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('3,513.11', $e->getMessage());
        }

        app(PaymentRegistrar::class)->apply($loan, 3513.11, 'TRANSFERENCIA', 'SPEI-L', kind: 'liquidacion');

        $this->assertSame('paid', $loan->fresh()->stage);
        $this->assertTrue($details->every(fn ($d) => $d->fresh()->status === 'S'));
        $this->assertSame(0.0, app(LoanPortfolio::class)->summary($loan->fresh())['principal_balance']);
    }

    public function test_paying_off_before_the_forced_months_adds_the_penalty(): void
    {
        ['loan' => $loan] = $this->approvedLoan();
        LoanProduct::where('type', 'NORMAL')->update(['min_forced_months' => 12]);

        $payoff = app(LoanLedger::class)->payoff($loan, today());

        $this->assertSame(30.0, $payoff['penalty']);
        $this->assertSame(4.8, $payoff['penalty_iva']);
        $this->assertSame(3547.91, $payoff['total']);
    }

    public function test_paying_every_installment_settles_the_loan_and_a_revert_reopens_it(): void
    {
        ['loan' => $loan, 'details' => $details, 'payment' => $payment] = $this->approvedLoan();
        $registrar = app(PaymentRegistrar::class);

        $details->each(fn ($d) => $registrar->markPaid($d, 'TRANSFERENCIA', 'REF'));

        $this->assertNotNull($loan->fresh()->paid_at);
        $this->assertSame('P', $payment->fresh()->status);
        $this->assertSame('paid', $loan->fresh()->stage);

        $registrar->revertDetail($details[2]);

        $this->assertNull($loan->fresh()->paid_at);
        $this->assertSame('N', $payment->fresh()->status);
        $this->assertSame('N', $details[2]->fresh()->status);
        $this->assertNull($details[2]->fresh()->payment_reference);
    }

    public function test_only_the_latest_payment_can_be_reverted(): void
    {
        ['details' => $details] = $this->approvedLoan();
        $registrar = app(PaymentRegistrar::class);
        $registrar->markPaid($details[0], 'EFECTIVO');
        $registrar->markPaid($details[1], 'EFECTIVO');

        $this->expectExceptionMessage('más reciente');
        $registrar->revertDetail($details[0]);
    }

    public function test_a_stripe_payment_cannot_be_reverted_from_here(): void
    {
        ['details' => $details] = $this->approvedLoan();
        app(PaymentRegistrar::class)->markPaid($details[0], 'STRIPE', 'pi_123');

        $this->expectException(InvalidArgumentException::class);
        app(PaymentRegistrar::class)->revertDetail($details[0]);
    }

    // ---- Pagos manuales (HTTP) ------------------------------------------------------------

    private function registerUrl(array $built, int $index = 0): string
    {
        return "/loans/{$built['loan']->id}/payments/{$built['details'][$index]->id}/register";
    }

    public function test_the_payments_page_of_a_loan_opens_for_staff(): void
    {
        $built = $this->approvedLoan();

        $this->actingAs($this->userWithRole('Advisor'))
            ->get("/loans/{$built['loan']->id}/payments")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Payments/PaymentsView')->has('loan')->has('summary')->where('isAdmin', false));
    }

    public function test_staff_can_register_a_manual_transfer(): void
    {
        $built = $this->approvedLoan();
        $advisor = $this->userWithRole('Advisor');

        $this->actingAs($advisor)->postJson($this->registerUrl($built), [
            'method' => 'TRANSFERENCIA', 'reference' => 'SPEI-123', 'paid_at' => '2026-10-20', 'notes' => 'Banorte',
        ])->assertOk()->assertJsonPath('data.summary.installments_paid', 1)->assertJsonPath('data.summary.overdue_installments', 1);

        $detail = $built['details'][0]->fresh();
        $this->assertSame('S', $detail->status);
        $this->assertSame('SPEI-123', $detail->payment_reference);
        $this->assertSame($advisor->id, $detail->registered_by);
        $this->assertSame('2026-10-20', Carbon::parse($detail->payed_at)->toDateString());
    }

    public function test_a_reference_is_required_for_bank_movements_but_not_for_cash(): void
    {
        $built = $this->approvedLoan();
        $staff = $this->userWithRole('Advisor');

        $this->actingAs($staff)->postJson($this->registerUrl($built), ['method' => 'TRANSFERENCIA'])
            ->assertStatus(422)->assertJsonValidationErrors('reference');

        $this->actingAs($staff)->postJson($this->registerUrl($built), ['method' => 'EFECTIVO'])->assertOk();
    }

    public function test_invalid_manual_payments_are_rejected(): void
    {
        $built = $this->approvedLoan();
        $staff = $this->userWithRole('Advisor');

        $this->actingAs($staff)->postJson($this->registerUrl($built), ['method' => 'CHEQUE'])->assertStatus(422);
        $this->actingAs($staff)->postJson($this->registerUrl($built), ['method' => 'EFECTIVO', 'paid_at' => '2026-12-01'])->assertStatus(422);
        $this->actingAs($staff)->postJson($this->registerUrl($built), ['method' => 'EFECTIVO', 'amount' => 0])->assertStatus(422);
        $this->actingAs($staff)->postJson($this->registerUrl($built), ['method' => 'EFECTIVO', 'kind' => 'otro'])->assertStatus(422);

        $this->assertSame('N', $built['details'][0]->fresh()->status);
    }

    public function test_an_installment_cannot_be_paid_twice(): void
    {
        $built = $this->approvedLoan();
        $staff = $this->userWithRole('Advisor');

        $this->actingAs($staff)->postJson($this->registerUrl($built), ['method' => 'EFECTIVO'])->assertOk();
        $this->actingAs($staff)->postJson($this->registerUrl($built), ['method' => 'EFECTIVO'])->assertStatus(409);
    }

    public function test_payments_cannot_be_registered_on_a_loan_that_is_not_approved(): void
    {
        $built = $this->approvedLoan();
        $built['loan']->forceFill(['approved_at' => null])->save();

        $this->actingAs($this->userWithRole('Advisor'))->postJson($this->registerUrl($built), ['method' => 'EFECTIVO'])->assertStatus(422);
    }

    public function test_customers_and_guests_cannot_register_payments(): void
    {
        $built = $this->approvedLoan();

        $this->actingAs($built['owner'])->postJson($this->registerUrl($built), ['method' => 'EFECTIVO'])->assertForbidden();
        $this->assertSame('N', $built['details'][0]->fresh()->status);
    }

    public function test_a_detail_from_another_loan_is_not_found(): void
    {
        $a = $this->approvedLoan();
        $b = $this->approvedLoan();

        $this->actingAs($this->userWithRole('Advisor'))
            ->postJson("/loans/{$a['loan']->id}/payments/{$b['details'][0]->id}/register", ['method' => 'EFECTIVO'])
            ->assertNotFound();
    }

    public function test_only_administrators_can_revert_a_payment(): void
    {
        $built = $this->approvedLoan();
        app(PaymentRegistrar::class)->markPaid($built['details'][0], 'EFECTIVO');
        $url = "/admin/payments/{$built['details'][0]->id}/revert";

        $this->actingAs($this->userWithRole('Advisor'))->postJson($url)->assertForbidden();
        $this->assertSame('S', $built['details'][0]->fresh()->status);

        $this->actingAs($this->userWithRole('Administrator'))->postJson($url)->assertOk();
        $this->assertSame('N', $built['details'][0]->fresh()->status);
    }

    // ---- Etapas ---------------------------------------------------------------------------

    public function test_the_stage_follows_the_loan_dates(): void
    {
        $loan = new Loan;
        $this->assertSame('incomplete', $loan->stage);

        $loan->submitted_at = now();
        $this->assertSame('received', $loan->stage);

        $loan->validation_started_at = now();
        $this->assertSame('in_validation', $loan->stage);

        $loan->validated_at = now();
        $this->assertSame('validated', $loan->stage);

        $loan->approved_at = now();
        $this->assertSame('approved', $loan->stage);

        $loan->disbursed_at = now();
        $this->assertSame('disbursed', $loan->stage);

        $loan->paid_at = now();
        $this->assertSame('paid', $loan->stage);

        $loan->reject_at = now();
        $this->assertSame('rejected', $loan->stage);
        $this->assertSame('Rechazada', $loan->stage_label);
    }

    public function test_staff_move_a_request_through_validation_and_disbursement(): void
    {
        $built = $this->approvedLoan();
        $loan = $built['loan'];
        $staff = $this->userWithRole('Advisor');
        $manager = $this->userWithRole('Gerente');
        $disbursement = ['method' => 'SPEI', 'reference' => 'RASTREO-123'];

        $this->actingAs($staff)->postJson("/loans/{$loan->id}/validation")->assertStatus(409);

        $loan->forceFill(['approved_at' => null])->save();
        $this->actingAs($staff)->postJson("/loans/{$loan->id}/validation")->assertStatus(409);

        $loan->forceFill(['submitted_at' => now()])->save();
        $this->actingAs($staff)->postJson("/loans/{$loan->id}/validation")->assertOk();
        $this->assertSame('in_validation', $loan->fresh()->stage);

        $this->actingAs($manager)->postJson("/loans/{$loan->id}/disburse", $disbursement)->assertStatus(409);

        $loan->forceFill(['approved_at' => now()])->save();
        $this->actingAs($staff)->postJson("/loans/{$loan->id}/disburse", $disbursement)->assertForbidden();
        $this->actingAs($manager)->postJson("/loans/{$loan->id}/disburse", ['method' => 'SPEI'])->assertStatus(422)->assertJsonValidationErrors('reference');
        $this->actingAs($manager)->postJson("/loans/{$loan->id}/disburse", ['method' => 'TARJETA', 'reference' => 'X'])->assertStatus(422)->assertJsonValidationErrors('method');
        $this->actingAs($manager)->postJson("/loans/{$loan->id}/disburse", $disbursement)->assertOk();

        $loan->refresh();
        $this->assertSame('disbursed', $loan->stage);
        $this->assertSame('SPEI', $loan->disbursement_method);
        $this->assertSame('RASTREO-123', $loan->disbursement_reference);
        $this->assertSame($manager->id, $loan->disbursed_by);

        $this->actingAs($manager)->postJson("/loans/{$loan->id}/disburse", $disbursement)->assertStatus(409);
    }

    public function test_disbursing_requires_a_saved_payment_plan(): void
    {
        $built = $this->approvedLoan();
        $built['details']->each->delete();
        $built['payment']->delete();

        $this->actingAs($this->userWithRole('Gerente'))
            ->postJson("/loans/{$built['loan']->id}/disburse", ['method' => 'EFECTIVO', 'reference' => 'REC-1'])
            ->assertStatus(422);
    }

    // ---- Pagos generales, liquidación y plan automático (HTTP) -----------------------------

    public function test_staff_register_a_general_payment_and_a_payoff(): void
    {
        $built = $this->approvedLoan();
        $loan = $built['loan'];
        $staff = $this->userWithRole('Advisor');

        $this->actingAs($staff)->postJson("/loans/{$loan->id}/payments/receive", ['method' => 'EFECTIVO'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->actingAs($staff)->postJson("/loans/{$loan->id}/payments/receive", ['method' => 'EFECTIVO', 'amount' => 1232.21])
            ->assertOk()->assertJsonPath('data.summary.installments_paid', 1);

        $total = $this->actingAs($staff)->getJson("/loans/{$loan->id}/payoff")->assertOk()->json('data.total');
        $this->assertSame(2280.9, $total);

        $this->actingAs($staff)->postJson("/loans/{$loan->id}/payments/receive", ['method' => 'EFECTIVO', 'amount' => $total, 'kind' => 'liquidacion'])
            ->assertOk()->assertJsonPath('data.summary.stage', 'paid');
        $this->assertSame(2, $loan->movements()->count());
    }

    public function test_the_plan_is_generated_from_the_product_and_cannot_be_typed_by_hand(): void
    {
        $built = $this->approvedLoan();
        $loan = $built['loan'];
        $built['details']->each->delete();
        $built['payment']->delete();
        $loan->forceFill(['start_at' => '2026-11-01'])->save();

        $this->actingAs($this->userWithRole('Advisor'))->postJson("/loans/{$loan->id}/payments", ['willSave' => true])->assertForbidden();

        $this->actingAs($this->userWithRole('Gerente'))
            ->postJson("/loans/{$loan->id}/payments", ['willSave' => true, 'annual_rate' => 99, 'months' => 360])
            ->assertOk();

        $payment = $loan->fresh()->payment;
        $this->assertSame(3, $payment->details()->count());
        $this->assertEqualsWithDelta(LoanProduct::forType('NORMAL')->annual_rate / 12 / 100, (float) $payment->month_rate, 0.000001);
        $this->assertSame('2026-11-01', $payment->details()->orderBy('number')->first()->due_date->toDateString());
    }

    public function test_an_administrator_reverts_the_latest_payment_from_the_history(): void
    {
        $built = $this->approvedLoan();
        $loan = $built['loan'];
        $registrar = app(PaymentRegistrar::class);
        $first = $registrar->apply($loan, 100, 'EFECTIVO');
        $second = $registrar->apply($loan, 200, 'EFECTIVO');

        $this->actingAs($this->userWithRole('Gerente'))->postJson("/admin/movements/{$second->id}/revert")->assertForbidden();

        $admin = $this->userWithRole('Administrator');
        $this->actingAs($admin)->postJson("/admin/movements/{$first->id}/revert")
            ->assertStatus(422)->assertJsonFragment(['message' => 'Primero revierte el pago más reciente del préstamo.']);
        $this->actingAs($admin)->postJson("/admin/movements/{$second->id}/revert")->assertOk();

        $this->assertNotNull($second->fresh()->reverted_at);
        $this->assertSame($admin->id, $second->fresh()->reverted_by);
        $this->assertSame(27.79, $built['details'][0]->fresh()->interest_paid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.reverted', 'loan_id' => $loan->id]);
    }
}
