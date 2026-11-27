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
            'bureau_score' => 680,
            'pld_result' => 'sin_coincidencias',
            'pld_evidence' => \Illuminate\Http\UploadedFile::fake()->create('cotejo.pdf', 50, 'application/pdf'),
        ], $override);
    }

    // ---- Política de crédito -------------------------------------------------------------------

    public function test_a_request_that_fails_the_credit_policy_needs_a_justification(): void
    {
        $loan = $this->pendingLoan();
        $loan->forceFill(['bureau_score' => 520])->save();
        $manager = $this->userWithRole('Gerente');

        $this->actingAs($manager)->putJson("/loans/{$loan->id}", ['status' => 'accepted'])
            ->assertStatus(422)
            ->assertJsonPath('policy.passes', false)
            ->assertJsonFragment(['message' => 'La solicitud no cumple la política de crédito (Calificación de Buró). Para aprobarla escribe una justificación de al menos 10 caracteres.']);
        $this->actingAs($manager)->putJson("/loans/{$loan->id}", ['status' => 'accepted', 'policy_override_reason' => 'Aval'])->assertStatus(422);
        $this->assertNull($loan->fresh()->approved_at);

        $this->actingAs($manager)->putJson("/loans/{$loan->id}", ['status' => 'accepted', 'policy_override_reason' => 'Cliente con 10 años de relación y aval solidario'])->assertOk();

        $loan->refresh();
        $this->assertNotNull($loan->approved_at);
        $this->assertFalse($loan->policy_result['passes']);
        $this->assertSame('Cliente con 10 años de relación y aval solidario', $loan->policy_override_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'loan.approved', 'loan_id' => $loan->id, 'note' => 'Aprobada sin cumplir la política: Cliente con 10 años de relación y aval solidario']);
    }

    public function test_a_request_that_meets_the_policy_is_approved_without_justification(): void
    {
        $loan = $this->pendingLoan();

        $this->actingAs($this->userWithRole('Gerente'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();

        $loan->refresh();
        $this->assertTrue($loan->policy_result['passes']);
        $this->assertSame(['capacity', 'employment', 'residence', 'bureau'], array_column($loan->policy_result['rules'], 'key'));
        $this->assertNull($loan->policy_override_reason);
    }

    public function test_the_policy_checks_capacity_seniority_residence_and_bureau(): void
    {
        $loan = $this->pendingLoan();
        $loan->customer->employment()->update(['monthly_income' => 6000, 'monthly_expenses' => 5500, 'seniority_working' => 0]);
        $loan->customer->addresses()->update(['years_living' => 0]);
        $loan->forceFill(['bureau_score' => 610])->save();

        $result = app(\App\Services\CreditPolicy::class)->evaluate($loan->fresh());
        $rules = collect($result['rules'])->keyBy('key');

        $this->assertFalse($result['passes']);
        $this->assertFalse($rules['capacity']['passes']);
        $this->assertStringContainsString('% del ingreso disponible', $rules['capacity']['value']);
        $this->assertFalse($rules['employment']['passes']);
        $this->assertFalse($rules['residence']['passes']);
        $this->assertTrue($rules['bureau']['passes']);

        // Un límite en 0 desactiva la regla
        \App\Models\LoanProduct::updateOrCreate(['type' => 'NORMAL'], array_merge(\App\Models\LoanProduct::defaults('NORMAL'), [
            'max_debt_ratio' => 0, 'min_employment_years' => 0, 'min_residence_years' => 0,
        ]));
        $result = app(\App\Services\CreditPolicy::class)->evaluate($loan->fresh());
        $this->assertTrue($result['passes']);
        $this->assertSame(['bureau'], array_column($result['rules'], 'key'));
    }

    public function test_the_preview_includes_the_policy_for_the_proposed_conditions(): void
    {
        $loan = $this->pendingLoan();
        // Ingreso disponible de 5,000: 2,000 a 12 meses cabe; 10,000 a 6 meses (cuota de unos 2,200) pasa del 35%
        $loan->customer->employment()->update(['monthly_income' => 7000, 'monthly_expenses' => 2000]);

        $staff = $this->userWithRole('Gerente');
        $this->actingAs($staff)->postJson("/loans/{$loan->id}/preview", ['amount' => 2000, 'term' => 12])
            ->assertOk()->assertJsonPath('data.policy.passes', true);
        $this->actingAs($staff)->postJson("/loans/{$loan->id}/preview", ['amount' => 10000, 'term' => 6])
            ->assertOk()->assertJsonPath('data.policy.passes', false);
    }

    public function test_the_analyst_must_capture_the_bureau_score(): void
    {
        $loan = $this->submittedLoan();

        $this->actingAs($this->userWithRole('Analista de crédito'))
            ->post("/loans/{$loan->id}/validate", $this->validation(['bureau_score' => null]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('bureau_score');
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
