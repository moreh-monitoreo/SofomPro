<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class LoanApprovalTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.documents' => 'local']);
        Storage::fake('local');
        $this->role('Customer');
    }

    private function pendingLoan(string $type = 'NORMAL'): Loan
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

        return Loan::create(['customer_id' => $customer->id, 'type' => $type, 'amount' => 5000, 'term' => 6, 'purpose' => 'Negocio']);
    }

    public function test_approving_keeps_the_requested_conditions_by_default(): void
    {
        $loan = $this->pendingLoan();
        $advisor = $this->userWithRole('Advisor');

        $this->actingAs($advisor)->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();

        $loan->refresh();
        $this->assertNotNull($loan->approved_at);
        $this->assertEquals(5000, $loan->amount);
        $this->assertSame(6, $loan->term);
        $this->assertNull($loan->requested_amount);
        $this->assertSame($advisor->id, $loan->approved_by);
        $this->assertSame('approved', $loan->stage);
        $this->assertTrue(User::where('email', 'laura@example.com')->firstOrFail()->hasRole('Customer'));
        $this->assertSame(5, $loan->documentations()->count());
        foreach ($loan->documentations as $document) {
            Storage::disk('local')->assertExists($document->path);
        }
    }

    public function test_the_team_can_approve_a_lower_amount_and_a_different_term(): void
    {
        $loan = $this->pendingLoan();

        $this->actingAs($this->userWithRole('Administrator'))
            ->putJson("/loans/{$loan->id}", ['status' => 'accepted', 'amount' => 3000, 'term' => 8])->assertOk();

        $loan->refresh();
        $this->assertEquals(3000, $loan->amount);
        $this->assertSame(8, $loan->term);
        $this->assertEquals(5000, $loan->requested_amount);
        $this->assertSame(6, $loan->requested_term);
    }

    public function test_the_approved_conditions_must_respect_the_product_limits(): void
    {
        $loan = $this->pendingLoan();
        $admin = $this->userWithRole('Administrator');

        $this->actingAs($admin)->putJson("/loans/{$loan->id}", ['status' => 'accepted', 'amount' => 90000])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->actingAs($admin)->putJson("/loans/{$loan->id}", ['status' => 'accepted', 'amount' => 100])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->actingAs($admin)->putJson("/loans/{$loan->id}", ['status' => 'accepted', 'term' => 36])->assertStatus(422)->assertJsonValidationErrors('term');

        $this->assertNull($loan->fresh()->approved_at);
        $this->assertSame(0, $loan->documentations()->count());
    }

    public function test_a_rejection_records_the_reason(): void
    {
        $loan = $this->pendingLoan();

        $this->actingAs($this->userWithRole('Advisor'))
            ->putJson("/loans/{$loan->id}", ['status' => 'rejected', 'reason' => 'Ingresos no comprobables'])->assertOk();

        $loan->refresh();
        $this->assertNotNull($loan->reject_at);
        $this->assertSame('Ingresos no comprobables', $loan->reject_reason);
        $this->assertSame('rejected', $loan->stage);
    }

    public function test_a_processed_request_cannot_be_processed_again(): void
    {
        $loan = $this->pendingLoan();
        $advisor = $this->userWithRole('Advisor');

        $this->actingAs($advisor)->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();
        $this->actingAs($advisor)->putJson("/loans/{$loan->id}", ['status' => 'rejected'])->assertStatus(400);
        $this->assertNull($loan->fresh()->reject_at);
    }

    public function test_customers_cannot_approve_requests(): void
    {
        $loan = $this->pendingLoan();

        $this->actingAs($this->userWithRole('Customer'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertForbidden();
        $this->assertNull($loan->fresh()->approved_at);
    }

    public function test_a_guarantee_loan_is_approved_with_the_guarantee_templates(): void
    {
        $loan = $this->pendingLoan('FUNERARIO');

        $this->actingAs($this->userWithRole('Advisor'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();

        $this->assertSame(5, $loan->documentations()->count());
    }

    public function test_the_internal_preview_accepts_amounts_the_public_simulator_does_not(): void
    {
        $loan = $this->pendingLoan();
        $advisor = $this->userWithRole('Advisor');

        $response = $this->actingAs($advisor)->postJson("/loans/{$loan->id}/preview", ['amount' => 3500, 'term' => 7])->assertOk();

        $this->assertEquals(3500, $response->json('data.amount'));
        $this->assertCount(7, $response->json('data.schedule'));
        $this->assertGreaterThan(0, $response->json('data.cat_con_iva'));

        $this->postJson('/api/simulator', ['amountCredit' => 3500, 'quantityPayments' => 7, 'type' => 'NORMAL'])->assertStatus(422);
    }

    public function test_the_internal_preview_respects_limits_and_is_for_staff_only(): void
    {
        $loan = $this->pendingLoan();

        $this->actingAs($this->userWithRole('Advisor'))->postJson("/loans/{$loan->id}/preview", ['amount' => 500000, 'term' => 6])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->actingAs($this->userWithRole('Customer'))->postJson("/loans/{$loan->id}/preview", ['amount' => 3000, 'term' => 6])->assertForbidden();
    }
}
