<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class VisibleFixesTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->role('Customer');
    }

    private function receivedLoan(): Loan
    {
        $customer = Customer::create([
            'status_id' => $this->customerStatus('LOAN CREATED')->id,
            'name' => 'Laura',
            'last_name' => 'Gómez',
            'gender' => 'Femenino',
            'email' => 'laura@example.com',
            'curp' => 'GOML900101MSLMRR01',
            'rfc' => 'GOML900101AB1',
        ]);

        return Loan::create(['customer_id' => $customer->id, 'type' => 'NORMAL', 'amount' => 3000, 'term' => 6, 'purpose' => 'Negocio', 'submitted_at' => now()]);
    }

    public function test_the_payment_statistics_open_even_when_an_approved_loan_has_no_plan(): void
    {
        $loan = $this->receivedLoan();
        $loan->forceFill(['approved_at' => now()])->save();

        $this->actingAs($this->userWithRole('Administrator'))
            ->get('/statistics/payments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Statistics/Payments')->where('statistics.paymentProgress', []));
    }

    public function test_the_dashboard_counts_the_same_received_requests_as_the_pipeline(): void
    {
        $this->receivedLoan();
        $admin = $this->userWithRole('Administrator');

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('staff.received', 1));

        $this->actingAs($admin)->get('/pipeline')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('counts.received', 1));
    }

    public function test_staff_get_the_team_summary_and_no_customer_loans(): void
    {
        $loan = $this->receivedLoan();
        $admin = $this->userWithRole('Administrator');
        $loan->customer->update(['user_id' => $admin->id]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('staff')->where('loans', []));
    }

    public function test_a_customer_sees_their_own_loans_and_no_team_summary(): void
    {
        $loan = $this->receivedLoan();
        $customerUser = $this->userWithRole('Customer');
        $loan->customer->update(['user_id' => $customerUser->id]);

        $this->actingAs($customerUser)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('staff', null)->has('loans', 1));
    }

    public function test_the_app_uses_mexico_city_time_and_spanish(): void
    {
        $this->assertSame('America/Mexico_City', config('app.timezone'));
        $this->assertSame('es', config('app.locale'));
    }

    public function test_validation_errors_come_in_spanish(): void
    {
        $data = $this->postJson('/request-credit/customer', ['name' => 'Ana'])->assertStatus(422)->json('errors');

        $this->assertNotEmpty($data);
        foreach ($data as $messages) {
            foreach ($messages as $message) {
                $this->assertDoesNotMatchRegularExpression('/\b(field|must|required|The)\b/', $message, $message);
            }
        }
    }

    public function test_the_work_seniority_must_be_a_whole_number_in_spanish(): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make(['seniority_working' => 'abc'], ['seniority_working' => 'required|integer|min:0|max:80']);

        $this->assertSame('los años de experiencia debe ser un número entero.', mb_strtolower($validator->errors()->first('seniority_working')));
    }
}
