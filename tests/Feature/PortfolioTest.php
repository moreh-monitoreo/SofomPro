<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\PaymentDetail;
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

    public function test_paying_the_overdue_installments_clears_the_arrears(): void
    {
        ['loan' => $loan, 'details' => $details] = $this->approvedLoan();
        $registrar = app(PaymentRegistrar::class);

        $registrar->markPaid($details[0], 'TRANSFERENCIA', 'REF1');
        $registrar->markPaid($details[1], 'EFECTIVO');

        $s = app(LoanPortfolio::class)->summary($loan->fresh());

        $this->assertFalse($s['is_overdue']);
        $this->assertSame(0, $s['days_overdue']);
        $this->assertSame(2320.0, $s['paid_amount']);
        $this->assertSame(1160.0, $s['pending_amount']);
        $this->assertSame(1000.0, $s['principal_balance']);
        $this->assertSame(3, $s['next_installment']['number']);
        $this->assertSame(15, $s['next_installment']['days_until_due']);
    }

    public function test_the_grace_days_delay_the_overdue_flag(): void
    {
        ['loan' => $loan] = $this->approvedLoan();
        config(['promas.overdue_grace_days' => 60]);

        $this->assertFalse(app(LoanPortfolio::class)->summary($loan)['is_overdue']);
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

    public function test_marking_an_installment_as_paid_is_idempotent(): void
    {
        ['details' => $details] = $this->approvedLoan();
        $registrar = app(PaymentRegistrar::class);

        $this->assertTrue($registrar->markPaid($details[0], 'TRANSFERENCIA', 'REF1', 1160, null, 7, 'nota'));
        $this->assertFalse($registrar->markPaid($details[0], 'EFECTIVO', 'OTRA'));

        $d = $details[0]->fresh();
        $this->assertSame('S', $d->status);
        $this->assertSame('TRANSFERENCIA', $d->payment_method);
        $this->assertSame('REF1', $d->payment_reference);
        $this->assertSame(7, $d->registered_by);
    }

    public function test_partial_payments_are_rejected(): void
    {
        ['details' => $details] = $this->approvedLoan();

        $this->expectException(InvalidArgumentException::class);
        app(PaymentRegistrar::class)->markPaid($details[0], 'EFECTIVO', null, 500);
    }

    public function test_paying_every_installment_settles_the_loan_and_a_revert_reopens_it(): void
    {
        ['loan' => $loan, 'details' => $details, 'payment' => $payment] = $this->approvedLoan();
        $registrar = app(PaymentRegistrar::class);

        $details->each(fn ($d) => $registrar->markPaid($d, 'TRANSFERENCIA', 'REF'));

        $this->assertNotNull($loan->fresh()->paid_at);
        $this->assertSame('P', $payment->fresh()->status);
        $this->assertSame('paid', $loan->fresh()->stage);

        $registrar->revert($details[2]);

        $this->assertNull($loan->fresh()->paid_at);
        $this->assertSame('N', $payment->fresh()->status);
        $this->assertSame('N', $details[2]->fresh()->status);
        $this->assertNull($details[2]->fresh()->payment_reference);
    }

    public function test_a_stripe_payment_cannot_be_reverted_from_here(): void
    {
        ['details' => $details] = $this->approvedLoan();
        app(PaymentRegistrar::class)->markPaid($details[0], 'STRIPE', 'pi_123', 1160);

        $this->expectException(InvalidArgumentException::class);
        app(PaymentRegistrar::class)->revert($details[0]);
    }

    // ---- Pagos manuales (HTTP) ------------------------------------------------------------

    private function registerUrl(array $built, int $index = 0): string
    {
        return "/loans/{$built['loan']->id}/payments/{$built['details'][$index]->id}/register";
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
        $this->actingAs($staff)->postJson($this->registerUrl($built), ['method' => 'EFECTIVO', 'amount' => 100])->assertStatus(422);

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
        $this->assertSame('received', $loan->stage);

        $loan->validation_started_at = now();
        $this->assertSame('in_validation', $loan->stage);

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

        $this->actingAs($staff)->postJson("/loans/{$loan->id}/validation")->assertStatus(409);

        $loan->forceFill(['approved_at' => null])->save();
        $this->actingAs($staff)->postJson("/loans/{$loan->id}/validation")->assertOk();
        $this->assertSame('in_validation', $loan->fresh()->stage);

        $this->actingAs($staff)->postJson("/loans/{$loan->id}/disburse")->assertStatus(409);

        $loan->forceFill(['approved_at' => now()])->save();
        $this->actingAs($staff)->postJson("/loans/{$loan->id}/disburse")->assertOk();
        $this->assertSame('disbursed', $loan->fresh()->stage);

        $this->actingAs($staff)->postJson("/loans/{$loan->id}/disburse")->assertStatus(409);
    }

    public function test_disbursing_requires_a_saved_payment_plan(): void
    {
        $built = $this->approvedLoan();
        $built['details']->each->delete();
        $built['payment']->delete();

        $this->actingAs($this->userWithRole('Advisor'))->postJson("/loans/{$built['loan']->id}/disburse")->assertStatus(422);
    }
}
