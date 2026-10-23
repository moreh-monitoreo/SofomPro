<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Services\PaymentRegistrar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
    }

    public function test_the_dashboard_shows_balance_next_payment_stage_and_arrears(): void
    {
        $built = $this->approvedLoan();

        $this->actingAs($built['owner'])->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('staff', null)
            ->has('loans', 1)
            ->where('loans.0.id', $built['loan']->id)
            ->where('loans.0.summary.customer_stage_label', 'Aprobada')
            ->where('loans.0.summary.pending_amount', 3480)
            ->where('loans.0.summary.paid_amount', 0)
            ->where('loans.0.summary.next_installment.due_date', '2026-09-05')
            ->where('loans.0.summary.is_overdue', true)
            ->where('loans.0.summary.overdue_amount', 2320)
            ->where('loans.0.summary.days_overdue', 46));
    }

    public function test_the_balance_updates_when_a_payment_is_registered(): void
    {
        $built = $this->approvedLoan();
        app(PaymentRegistrar::class)->markPaid($built['details'][0], 'STRIPE', 'pi_1', 1160);

        $this->actingAs($built['owner'])->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('loans.0.summary.pending_amount', 2320)
            ->where('loans.0.summary.paid_amount', 1160)
            ->where('loans.0.summary.installments_paid', 1)
            ->where('loans.0.summary.overdue_installments', 1));
    }

    public function test_the_customer_sees_the_request_status_in_plain_words(): void
    {
        $owner = $this->userWithRole('Customer');
        $customer = Customer::create(['user_id' => $owner->id, 'status_id' => $this->customerStatus('LOAN CREATED')->id, 'name' => 'Ana', 'last_name' => 'Pérez', 'gender' => 'Femenino']);
        $loan = Loan::create(['customer_id' => $customer->id, 'amount' => 2000, 'term' => 6]);

        $label = fn () => $this->actingAs($owner)->get('/dashboard')->viewData('page')['props']['loans'][0]['summary']['customer_stage_label'];

        $this->assertSame('En revisión', $label());

        $loan->forceFill(['validation_started_at' => now()])->save();
        $this->assertSame('En revisión', $label());

        $loan->forceFill(['approved_at' => now()])->save();
        $this->assertSame('Aprobada', $label());

        $loan->forceFill(['disbursed_at' => now()])->save();
        $this->assertSame('Desembolsada', $label());

        $loan->forceFill(['reject_at' => now()])->save();
        $this->assertSame('Rechazada', $label());
    }

    public function test_a_customer_only_sees_their_own_loans(): void
    {
        $mine = $this->approvedLoan();
        $other = $this->approvedLoan();

        $this->actingAs($mine['owner'])->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('loans', 1)->where('loans.0.id', $mine['loan']->id));

        $this->actingAs($other['owner'])->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('loans', 1)->where('loans.0.id', $other['loan']->id));
    }

    public function test_the_staff_dashboard_has_team_metrics(): void
    {
        $this->approvedLoan();
        $received = $this->approvedLoan();
        $received['loan']->forceFill(['approved_at' => null])->save();

        $this->actingAs($this->userWithRole('Advisor'))->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('staff.received', 1)
            ->where('staff.approved', 1)
            ->where('staff.overdue_clients', 2)
            ->has('loans', 0));
    }

    public function test_the_payments_page_includes_the_summary(): void
    {
        $built = $this->approvedLoan();

        $this->actingAs($built['owner'])
            ->get(route('loans.show.payments', ['customer' => $built['customer']->id, 'loan' => $built['loan']->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Payments')
                ->where('summary.pending_amount', 3480)
                ->where('isStaff', false)
                ->has('loan.payment.details', 3));
    }

    // ---- Recibos PDF -----------------------------------------------------------------------

    private function receiptUrl(array $built, int $index = 0): string
    {
        return route('loans.payments.receipt', ['loan' => $built['loan']->id, 'detail' => $built['details'][$index]->id]);
    }

    private function pay(array $built, int $index = 0, string $method = 'TRANSFERENCIA'): void
    {
        app(PaymentRegistrar::class)->markPaid($built['details'][$index], $method, 'SPEI-998877', 1160);
    }

    public function test_the_owner_can_download_a_pdf_receipt_of_a_paid_installment(): void
    {
        $built = $this->approvedLoan();
        $this->pay($built);

        $response = $this->actingAs($built['owner'])->get($this->receiptUrl($built))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(sprintf('REC-%06d-01.pdf', $built['loan']->id), $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_staff_can_download_any_receipt(): void
    {
        $built = $this->approvedLoan();
        $this->pay($built, 1, 'EFECTIVO');

        $this->actingAs($this->userWithRole('Advisor'))->get($this->receiptUrl($built, 1))->assertOk();
    }

    public function test_another_customer_cannot_download_the_receipt(): void
    {
        $built = $this->approvedLoan();
        $this->pay($built);

        $this->actingAs($this->userWithRole('Customer'))->get($this->receiptUrl($built))->assertForbidden();
        auth()->logout();
        $this->get($this->receiptUrl($built))->assertRedirect('/login');
    }

    public function test_an_unpaid_installment_has_no_receipt(): void
    {
        $built = $this->approvedLoan();

        $this->actingAs($built['owner'])->get($this->receiptUrl($built))->assertNotFound();
    }

    public function test_an_installment_from_another_loan_is_not_found(): void
    {
        $a = $this->approvedLoan();
        $b = $this->approvedLoan();
        $this->pay($b);

        $this->actingAs($a['owner'])->get(route('loans.payments.receipt', ['loan' => $a['loan']->id, 'detail' => $b['details'][0]->id]))->assertNotFound();
    }

    public function test_the_receipt_view_contains_the_legal_data_and_the_breakdown(): void
    {
        config(['promas.legal_name' => 'Promas Servicios Financieros SA de CV', 'promas.rfc' => 'PSF240101AB1', 'promas.address' => 'Culiacán, Sinaloa']);
        $built = $this->approvedLoan();
        $this->pay($built);

        $html = view('pdf.receipt', [
            'company' => config('promas'), 'number' => 'REC-000001-01', 'issuedAt' => now(), 'loan' => $built['loan']->load('payment.details'),
            'folio' => 'PRM-000001', 'customer' => $built['customer'], 'detail' => $built['details'][0]->fresh(), 'installments' => 3, 'vat' => 0.0,
            'method' => 'Transferencia bancaria', 'paidTotal' => 1160.0, 'pendingTotal' => 2320.0,
        ])->render();

        foreach (['Promas Servicios Financieros SA de CV', 'PSF240101AB1', 'Culiacán, Sinaloa', 'Ana Pérez', 'SPEI-998877', 'Interés ordinario', 'IVA sobre intereses', '1,160.00', 'no sustituye al comprobante fiscal'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }
}
