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

        return $this->readyForApproval(Loan::create(['customer_id' => $customer->id, 'type' => $type, 'amount' => 5000, 'term' => 6, 'purpose' => 'Negocio']));
    }

    public function test_approving_keeps_the_requested_conditions_by_default(): void
    {
        $loan = $this->pendingLoan();
        $advisor = $this->userWithRole('Gerente');

        $this->actingAs($advisor)->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();

        $loan->refresh();
        $this->assertNotNull($loan->approved_at);
        $this->assertEquals(5000, $loan->amount);
        $this->assertSame(6, $loan->term);
        $this->assertEquals(5000, $loan->requested_amount);
        $this->assertSame($advisor->id, $loan->approved_by);
        $this->assertSame('approved', $loan->stage);
        $this->assertTrue(User::where('email', 'laura@example.com')->firstOrFail()->hasRole('Customer'));
        // 4 documentos del cliente + contrato y 4 anexos generados
        $this->assertSame(9, $loan->documentations()->count());
        $this->assertSame(6, $loan->payment->details()->count());
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
        $this->assertSame(4, $loan->documentations()->count());
    }

    public function test_a_rejection_records_the_reason(): void
    {
        $loan = $this->pendingLoan();

        $this->actingAs($this->userWithRole('Analista de crédito'))
            ->putJson("/loans/{$loan->id}", ['status' => 'rejected', 'reason' => 'Ingresos no comprobables'])->assertOk();

        $loan->refresh();
        $this->assertNotNull($loan->reject_at);
        $this->assertSame('Ingresos no comprobables', $loan->reject_reason);
        $this->assertSame('rejected', $loan->stage);
    }

    public function test_a_processed_request_cannot_be_processed_again(): void
    {
        $loan = $this->pendingLoan();
        $advisor = $this->userWithRole('Gerente');

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

        $this->actingAs($this->userWithRole('Gerente'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();

        // 4 documentos del cliente + contrato y 4 anexos generados
        $this->assertSame(9, $loan->documentations()->count());
        $this->assertSame(6, $loan->payment->details()->count());
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

    // ---- Validación (Buró y PLD) y doble firma -----------------------------------------------

    private function submittedLoan(): Loan
    {
        $loan = $this->pendingLoan();
        $loan->forceFill(['validated_at' => null, 'validated_by' => null, 'validation_started_at' => null, 'pld_result' => null, 'bureau_folio' => null, 'bureau_result' => null])->save();

        return $loan;
    }

    private function validation(array $override = []): array
    {
        return array_merge([
            'bureau_folio' => 'BC-778899',
            'bureau_result' => 'Score 680, sin atrasos',
            'pld_result' => 'sin_coincidencias',
            'pld_evidence' => \Illuminate\Http\UploadedFile::fake()->create('cotejo.pdf', 50, 'application/pdf'),
        ], $override);
    }

    public function test_an_analyst_validates_with_bureau_and_pld_and_a_manager_approves(): void
    {
        $loan = $this->submittedLoan();
        $analyst = $this->userWithRole('Analista de crédito');

        $this->actingAs($analyst)->post("/loans/{$loan->id}/validate", $this->validation(), ['Accept' => 'application/json'])->assertOk();

        $loan->refresh();
        $this->assertSame('validated', $loan->stage);
        $this->assertSame('BC-778899', $loan->bureau_folio);
        $this->assertSame($analyst->id, $loan->validated_by);
        Storage::disk('local')->assertExists($loan->pld_evidence_path);
        $this->assertTrue($loan->documentations()->where('type', 'Cotejo PLD')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'loan.validated', 'loan_id' => $loan->id, 'user_id' => $analyst->id]);

        // El analista no puede aprobar lo que validó, ni siquiera siendo además Gerente
        $analyst->assignRole($this->role('Gerente'));
        $this->actingAs($analyst)->putJson("/loans/{$loan->id}", ['status' => 'accepted'])
            ->assertForbidden()->assertJsonFragment(['message' => 'Quien validó la solicitud no puede aprobarla; debe hacerlo otra persona.']);

        $manager = $this->userWithRole('Gerente');
        $this->actingAs($manager)->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();
        $this->assertSame($manager->id, $loan->fresh()->approved_by);
    }

    public function test_the_validation_requires_every_field_and_the_right_role(): void
    {
        $loan = $this->submittedLoan();

        $this->actingAs($this->userWithRole('Advisor'))->post("/loans/{$loan->id}/validate", $this->validation(), ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->userWithRole('Gerente'))->post("/loans/{$loan->id}/validate", $this->validation(), ['Accept' => 'application/json'])->assertForbidden();

        $analyst = $this->userWithRole('Analista de crédito');
        $this->actingAs($analyst)->post("/loans/{$loan->id}/validate", $this->validation(['pld_evidence' => null]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('pld_evidence');
        $this->actingAs($analyst)->post("/loans/{$loan->id}/validate", $this->validation(['bureau_folio' => '']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('bureau_folio');
        $this->actingAs($analyst)->post("/loans/{$loan->id}/validate", $this->validation(['pld_result' => 'tal vez']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('pld_result');

        $this->assertNull($loan->fresh()->validated_at);
    }

    public function test_a_request_cannot_be_approved_without_validation(): void
    {
        $loan = $this->submittedLoan();

        $this->actingAs($this->userWithRole('Administrator'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'La solicitud debe estar validada (Buró de Crédito y cotejo PLD) antes de aprobarse.']);
        $this->assertNull($loan->fresh()->approved_at);
    }

    public function test_pld_matches_block_the_approval(): void
    {
        $loan = $this->pendingLoan();
        $loan->forceFill(['pld_result' => 'con_coincidencias'])->save();

        $this->actingAs($this->userWithRole('Gerente'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertStatus(422);
        $this->assertNull($loan->fresh()->approved_at);
    }

    public function test_missing_required_documents_block_the_approval(): void
    {
        $loan = $this->pendingLoan();
        $loan->documentations()->where('type', 'Comprobante de domicilio')->delete();

        $this->actingAs($this->userWithRole('Gerente'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])
            ->assertStatus(422)->assertJsonPath('missing', ['Comprobante de domicilio']);

        // El producto define qué se exige
        \App\Models\LoanProduct::where('type', 'NORMAL')->update(['required_documents' => json_encode(['INE (frente)'])]);
        $this->actingAs($this->userWithRole('Gerente'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();
    }

    public function test_only_managers_and_administrators_approve_and_advisors_cannot_reject(): void
    {
        $loan = $this->pendingLoan();

        $this->actingAs($this->userWithRole('Advisor'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertForbidden();
        $this->actingAs($this->userWithRole('Analista de crédito'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertForbidden();
        $this->actingAs($this->userWithRole('Advisor'))->putJson("/loans/{$loan->id}", ['status' => 'rejected'])->assertForbidden();
        $this->assertSame('validated', $loan->fresh()->stage);
    }

    public function test_the_migration_creates_the_new_roles(): void
    {
        $this->assertTrue(\Spatie\Permission\Models\Role::where('name', 'Analista de crédito')->exists());
        $this->assertTrue(\Spatie\Permission\Models\Role::where('name', 'Gerente')->exists());
    }
}
