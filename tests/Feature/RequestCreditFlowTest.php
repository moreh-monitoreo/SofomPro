<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use Database\Seeders\StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class RequestCreditFlowTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusesSeeder::class);
    }

    private function personal(array $override = []): array
    {
        return array_merge([
            'name' => 'Ana',
            'last_name' => 'Pérez',
            'maiden_name' => 'López',
            'gender' => 'Femenino',
            'birthday' => '1990-01-01',
            'curp' => 'PEPA900101MSLRRN01',
            'rfc' => 'PEPA900101AB1',
            'state' => 'Sinaloa',
            'email' => 'ana@example.com',
            'phone' => '6671234567',
            'accept_privacy' => true,
            'authorize_credit_check' => true,
        ], $override);
    }

    private function startRequest(array $override = []): array
    {
        $response = $this->postJson('/request-credit/customer', $this->personal($override))->assertCreated();

        return $response->json('data');
    }

    public function test_the_first_step_stores_the_customer_and_the_consents(): void
    {
        $data = $this->startRequest();

        $customer = Customer::findOrFail($data['id']);
        $this->assertNotNull($customer->privacy_accepted_at);
        $this->assertNotNull($customer->credit_check_authorized_at);
        $this->assertSame(config('promas.consent_version'), $customer->consent_version);
        $this->assertNotNull($customer->consent_ip);
        $this->assertSame($customer->id, Loan::findOrFail($data['loan']['id'])->customer_id);
    }

    public function test_privacy_and_credit_bureau_consents_are_mandatory(): void
    {
        $this->postJson('/request-credit/customer', $this->personal(['accept_privacy' => false]))
            ->assertStatus(422)->assertJsonValidationErrors('accept_privacy');

        $this->postJson('/request-credit/customer', $this->personal(['authorize_credit_check' => false]))
            ->assertStatus(422)->assertJsonValidationErrors('authorize_credit_check');

        $payload = $this->personal();
        unset($payload['accept_privacy'], $payload['authorize_credit_check']);
        $this->postJson('/request-credit/customer', $payload)->assertStatus(422);

        $this->assertSame(0, Customer::count());
    }

    public function test_curp_and_rfc_are_validated_automatically(): void
    {
        $this->postJson('/request-credit/customer', $this->personal(['curp' => 'ABC123']))
            ->assertStatus(422)->assertJsonValidationErrors('curp');

        $this->postJson('/request-credit/customer', $this->personal(['rfc' => '12345']))
            ->assertStatus(422)->assertJsonValidationErrors('rfc');

        $this->postJson('/request-credit/customer', $this->personal(['curp' => 'PEPA901301MSLRRN01']))
            ->assertStatus(422)->assertJsonValidationErrors('curp');
    }

    public function test_curp_and_rfc_must_match_the_birthday_and_gender(): void
    {
        $this->postJson('/request-credit/customer', $this->personal(['birthday' => '1991-02-02']))
            ->assertStatus(422)->assertJsonValidationErrors(['curp', 'rfc']);

        $this->postJson('/request-credit/customer', $this->personal(['gender' => 'Masculino']))
            ->assertStatus(422)->assertJsonValidationErrors('curp');
    }

    public function test_lowercase_curp_and_rfc_are_accepted_and_normalized(): void
    {
        $data = $this->startRequest(['curp' => 'pepa900101mslrrn01', 'rfc' => 'pepa900101ab1']);

        $this->assertSame('PEPA900101MSLRRN01', Customer::find($data['id'])->curp);
        $this->assertSame('PEPA900101AB1', Customer::find($data['id'])->rfc);
    }

    public function test_the_applicant_must_be_an_adult(): void
    {
        $this->postJson('/request-credit/customer', $this->personal(['birthday' => now()->subYears(17)->format('Y-m-d')]))
            ->assertStatus(422)->assertJsonValidationErrors('birthday');
    }

    public function test_later_steps_require_the_session_that_started_the_request(): void
    {
        $data = $this->startRequest();
        $customerId = $data['id'];

        $this->flushSession();

        $this->postJson("/request-credit/customer/{$customerId}/address", ['street' => 'Calle 1'])->assertForbidden();
        $this->postJson("/request-credit/customer/{$customerId}", ['civil_status' => 'Soltero'])->assertForbidden();
        $this->postJson("/request-credit/customer/{$customerId}/employment", ['monthly_income' => 1])->assertForbidden();
        $this->postJson("/request-credit/customer/{$customerId}/loan", ['amount' => 1000])->assertForbidden();
        $this->postJson("/request-credit/customer/{$customerId}/documents", [])->assertForbidden();
    }

    public function test_the_street_numbers_accept_letters_and_dashes(): void
    {
        $data = $this->startRequest();
        $base = ['street' => 'Av. Reforma', 'suburb' => 'Centro', 'postal_code' => '80000', 'years_living' => 3, 'type' => 'Propia'];

        $this->postJson("/request-credit/customer/{$data['id']}/address", $base + ['outdoor_number' => '12-A', 'interior_number' => 'B-203'])->assertCreated();

        $this->assertDatabaseHas('customer_addresses', ['customer_id' => $data['id'], 'outdoor_number' => '12-A', 'interior_number' => 'B-203']);
    }

    public function test_the_street_numbers_reject_symbols_and_empty_values(): void
    {
        $data = $this->startRequest();
        $base = ['street' => 'Av. Reforma', 'suburb' => 'Centro', 'postal_code' => '80000', 'years_living' => 3, 'type' => 'Propia'];

        $this->postJson("/request-credit/customer/{$data['id']}/address", $base + ['outdoor_number' => '<b>1</b>'])->assertJsonValidationErrors('outdoor_number');
        $this->postJson("/request-credit/customer/{$data['id']}/address", $base + ['outdoor_number' => ''])->assertJsonValidationErrors('outdoor_number');
        $this->postJson("/request-credit/customer/{$data['id']}/address", $base + ['outdoor_number' => str_repeat('1', 21)])->assertJsonValidationErrors('outdoor_number');
    }

    public function test_a_session_cannot_edit_another_applicants_record(): void
    {
        $victim = Customer::create([
            'status_id' => $this->customerStatus('PROFILE REGISTERED')->id,
            'name' => 'Víctima',
            'last_name' => 'Real',
            'gender' => 'Femenino',
        ]);

        $this->startRequest();

        $this->postJson("/request-credit/customer/{$victim->id}", ['name' => 'Hackeado', 'civil_status' => 'Soltero'])->assertForbidden();
        $this->assertSame('Víctima', $victim->fresh()->name);
    }

    public function test_an_existing_customer_with_an_account_cannot_be_overwritten(): void
    {
        $owner = $this->userWithRole('Customer');
        $this->approvedLoan($owner);

        $this->postJson('/request-credit/customer', $this->personal(['name' => 'Atacante', 'email' => 'atacante@example.com']))
            ->assertStatus(409);

        $customer = Customer::where('curp', 'PEPA900101MSLRRN01')->first();
        $this->assertSame('Ana', $customer->name);
        $this->assertSame($owner->email, $customer->email);
    }

    public function test_the_public_form_cannot_approve_or_mark_a_loan_as_paid(): void
    {
        $data = $this->startRequest();

        $this->postJson("/request-credit/customer/{$data['id']}/loan", [
            'amount' => 3000,
            'term' => 6,
            'purpose' => 'Negocio',
            'purpose_description' => 'Comprar producto',
            'type' => 'NORMAL',
            'approved_at' => now()->toDateTimeString(),
            'start_at' => now()->toDateTimeString(),
            'paid_at' => now()->toDateTimeString(),
        ])->assertCreated();

        $loan = Loan::findOrFail($data['loan']['id']);
        $this->assertNull($loan->approved_at);
        $this->assertNull($loan->paid_at);
        $this->assertNull($loan->start_at);
        $this->assertSame('Pendiente', $loan->status);
    }

    public function test_income_and_expenses_are_required_in_the_employment_step(): void
    {
        $data = $this->startRequest();

        $this->postJson("/request-credit/customer/{$data['id']}/employment", ['type' => 'Empleado formal'])
            ->assertStatus(422)->assertJsonValidationErrors(['area', 'seniority_working', 'monthly_income', 'monthly_expenses']);

        $this->postJson("/request-credit/customer/{$data['id']}/employment", [
            'type' => 'Empleado formal',
            'area' => 'Servicios',
            'main_activity' => 'Comercio',
            'seniority_working' => 3,
            'monthly_income' => 12000,
            'monthly_expenses' => 7500,
        ])->assertCreated();

        $this->assertEquals(7500, Customer::find($data['id'])->employment->monthly_expenses);
    }

    private function documents(array $override = []): array
    {
        return array_merge([
            'ine_front' => UploadedFile::fake()->image('ine-frente.jpg'),
            'ine_back' => UploadedFile::fake()->image('ine-reverso.png'),
            'proof_of_address' => UploadedFile::fake()->create('luz.pdf', 200, 'application/pdf'),
            'bank_statement' => UploadedFile::fake()->create('estado.pdf', 300, 'application/pdf'),
        ], $override);
    }

    private function requestWithLoan(): array
    {
        $data = $this->startRequest();
        $this->postJson("/request-credit/customer/{$data['id']}/loan", [
            'amount' => 3000, 'term' => 6, 'purpose' => 'Negocio', 'purpose_description' => 'x', 'type' => 'NORMAL',
        ])->assertCreated();

        return $data;
    }

    public function test_the_four_required_documents_are_stored_in_the_documents_disk(): void
    {
        config(['filesystems.documents' => 'local']);
        Storage::fake('local');
        $data = $this->requestWithLoan();

        $this->post("/request-credit/customer/{$data['id']}/documents", $this->documents(), ['Accept' => 'application/json'])->assertCreated();

        $loan = Loan::findOrFail($data['loan']['id']);
        $this->assertSame(
            ['Comprobante de domicilio', 'Estado de cuenta bancario', 'INE (frente)', 'INE (reverso)'],
            $loan->documentations()->orderBy('type')->pluck('type')->all()
        );
        foreach ($loan->documentations as $document) {
            Storage::disk('local')->assertExists($document->path);
        }
    }

    public function test_all_four_documents_are_required_and_must_be_valid_files(): void
    {
        config(['filesystems.documents' => 'local']);
        Storage::fake('local');
        $data = $this->requestWithLoan();

        $incomplete = $this->documents();
        unset($incomplete['bank_statement']);
        $this->post("/request-credit/customer/{$data['id']}/documents", $incomplete, ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('bank_statement');

        $this->post("/request-credit/customer/{$data['id']}/documents", $this->documents([
            'ine_front' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('ine_front');

        $this->post("/request-credit/customer/{$data['id']}/documents", $this->documents([
            'ine_back' => UploadedFile::fake()->create('enorme.pdf', 6000, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('ine_back');

        $this->assertSame(0, Loan::find($data['loan']['id'])->documentations()->count());
    }

    public function test_uploading_again_replaces_the_previous_documents(): void
    {
        config(['filesystems.documents' => 'local']);
        Storage::fake('local');
        $data = $this->requestWithLoan();

        $this->post("/request-credit/customer/{$data['id']}/documents", $this->documents(), ['Accept' => 'application/json'])->assertCreated();
        $first = Loan::find($data['loan']['id'])->documentations()->where('type', 'INE (frente)')->first()->path;

        $this->post("/request-credit/customer/{$data['id']}/documents", $this->documents([
            'ine_front' => UploadedFile::fake()->create('nueva.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(4, Loan::find($data['loan']['id'])->documentations()->count());
        Storage::disk('local')->assertMissing($first);
    }

    // ---- Límites del producto en el paso del préstamo ---------------------------------------

    private function loanPayload(array $override = []): array
    {
        return array_merge([
            'amount' => 3000, 'term' => 6, 'purpose' => 'Negocio', 'purpose_description' => 'Comprar producto', 'type' => 'NORMAL',
        ], $override);
    }

    public function test_the_loan_step_rejects_amounts_and_terms_outside_the_product_limits(): void
    {
        $data = $this->startRequest();
        $url = "/request-credit/customer/{$data['id']}/loan";

        $this->postJson($url, $this->loanPayload(['amount' => 500000]))->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson($url, $this->loanPayload(['amount' => 1000]))->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson($url, $this->loanPayload(['amount' => 3500]))->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson($url, $this->loanPayload(['term' => 1]))->assertStatus(422)->assertJsonValidationErrors('term');
        $this->postJson($url, $this->loanPayload(['term' => 60]))->assertStatus(422)->assertJsonValidationErrors('term');
        $this->postJson($url, $this->loanPayload(['term' => 7.5]))->assertStatus(422)->assertJsonValidationErrors('term');

        $this->assertNull(Loan::find($data['loan']['id'])->amount);
    }

    public function test_the_error_messages_tell_the_customer_the_allowed_range(): void
    {
        $data = $this->startRequest();

        $this->postJson("/request-credit/customer/{$data['id']}/loan", $this->loanPayload(['amount' => 500000]))
            ->assertJsonPath('errors.amount.0', 'El monto máximo es de $10,000.');

        $this->postJson("/request-credit/customer/{$data['id']}/loan", $this->loanPayload(['term' => 24]))
            ->assertJsonPath('errors.term.0', 'El plazo máximo es de 12 meses.');
    }

    public function test_the_limits_boundaries_are_accepted(): void
    {
        foreach ([[2000, 6], [10000, 12]] as [$amount, $term]) {
            $this->flushSession();
            $data = $this->startRequest(['curp' => $amount === 2000 ? 'PEPA900101MSLRRN01' : 'PEPA900101MSLRRN01']);

            $this->postJson("/request-credit/customer/{$data['id']}/loan", $this->loanPayload(['amount' => $amount, 'term' => $term]))->assertCreated();
        }

        $this->assertEquals(10000, Loan::latest('id')->first()->amount);
    }

    public function test_the_limits_follow_the_product_configured_in_the_database(): void
    {
        LoanProduct::where('type', 'NORMAL')->update(['min_amount' => 1000, 'max_amount' => 20000, 'amount_step' => 500, 'min_term' => 3, 'max_term' => 24]);
        $data = $this->startRequest();

        $this->postJson("/request-credit/customer/{$data['id']}/loan", $this->loanPayload(['amount' => 15500, 'term' => 24]))->assertCreated();
        $this->postJson("/request-credit/customer/{$data['id']}/loan", $this->loanPayload(['amount' => 15600]))->assertStatus(422);
    }

    public function test_a_guarantee_loan_uses_the_guarantee_product_limits(): void
    {
        LoanProduct::where('type', 'FUNERARIO')->update(['max_amount' => 5000]);
        $data = $this->startRequest();
        $url = "/request-credit/customer/{$data['id']}/loan";

        $guarantee = $this->loanPayload(['type' => 'FUNERARIO', 'policy_folio' => 'PF-001']);
        $this->postJson($url, array_merge($guarantee, ['amount' => 8000]))->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson($url, array_merge($guarantee, ['amount' => 8000, 'type' => 'NORMAL']))->assertCreated();
        $this->postJson($url, array_merge($guarantee, ['amount' => 4000]))->assertCreated();
    }

    public function test_an_inactive_product_cannot_be_requested(): void
    {
        LoanProduct::where('type', 'NORMAL')->update(['active' => false]);
        $data = $this->startRequest();

        $this->postJson("/request-credit/customer/{$data['id']}/loan", $this->loanPayload())->assertStatus(422);
        $this->assertNull(Loan::find($data['loan']['id'])->amount);
    }
}
