<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PostalCode;
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
        $base = ['street' => 'Av. Reforma', 'suburb' => 'Centro', 'postal_code' => '80000', 'municipality' => 'Culiacán', 'state' => 'Sinaloa', 'years_living' => 3, 'type' => 'Propia'];

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

    public function test_the_postal_code_catalog_fills_municipality_and_state(): void
    {
        PostalCode::insert([
            ['code' => '80000', 'settlement' => 'Centro', 'settlement_type' => 'Colonia', 'municipality' => 'Culiacán', 'state' => 'Sinaloa', 'city' => 'Culiacán Rosales'],
            ['code' => '80000', 'settlement' => 'Almada', 'settlement_type' => 'Colonia', 'municipality' => 'Culiacán', 'state' => 'Sinaloa', 'city' => 'Culiacán Rosales'],
        ]);

        $this->getJson('/postal-codes/80000')->assertOk()
            ->assertJsonPath('data.municipality', 'Culiacán')
            ->assertJsonPath('data.state', 'Sinaloa')
            ->assertJsonPath('data.settlements', ['Almada', 'Centro']);
        $this->getJson('/postal-codes/99999')->assertNotFound();

        // Con el código en el catálogo no hace falta escribir municipio ni estado, y lo escrito no los reemplaza
        $data = $this->startRequest();
        $this->postJson("/request-credit/customer/{$data['id']}/address", [
            'street' => 'Av. Obregón', 'outdoor_number' => '10', 'suburb' => 'Centro', 'postal_code' => '80000',
            'state' => 'Jalisco', 'years_living' => 2, 'type' => 'Propietario',
        ])->assertCreated();

        $this->assertDatabaseHas('customer_addresses', ['customer_id' => $data['id'], 'municipality' => 'Culiacán', 'state' => 'Sinaloa']);
    }

    public function test_without_the_catalog_municipality_and_state_must_be_typed(): void
    {
        $data = $this->startRequest();

        $this->postJson("/request-credit/customer/{$data['id']}/address", [
            'street' => 'Av. Obregón', 'outdoor_number' => '10', 'suburb' => 'Centro', 'postal_code' => '80000', 'years_living' => 2, 'type' => 'Propietario',
        ])->assertStatus(422)->assertJsonValidationErrors(['municipality', 'state']);

        $this->postJson("/request-credit/customer/{$data['id']}/address", [
            'street' => 'Av. Obregón', 'outdoor_number' => '10', 'suburb' => 'Centro', 'postal_code' => '8000', 'years_living' => 2, 'type' => 'Propietario',
        ])->assertStatus(422)->assertJsonValidationErrors('postal_code');
    }

    public function test_the_sepomex_file_is_imported(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cp');
        file_put_contents($file, implode("\r\n", [
            mb_convert_encoding('El Catálogo Nacional de Códigos Postales, es elaborado por Correos de México', 'ISO-8859-1', 'UTF-8'),
            'd_codigo|d_asenta|d_tipo_asenta|D_mnpio|d_estado|d_ciudad|d_CP|c_estado|c_oficina|c_CP|c_tipo_asenta|c_mnpio|id_asenta_cpcons|d_zona|c_cve_ciudad',
            mb_convert_encoding('80000|Centro|Colonia|Culiacán|Sinaloa|Culiacán Rosales|80001|25|80001||09|006|0001|Urbano|01', 'ISO-8859-1', 'UTF-8'),
            mb_convert_encoding('01000|San Ángel|Colonia|Álvaro Obregón|Ciudad de México|Ciudad de México|01001|09|01001||09|010|0001|Urbano|01', 'ISO-8859-1', 'UTF-8'),
        ]));

        $this->artisan('sepomex:import', ['file' => $file])->assertSuccessful();
        $this->assertSame(2, PostalCode::count());
        $this->assertSame('Álvaro Obregón', PostalCode::lookup('01000')['municipality']);

        // El CSV del espejo (UTF-8 con BOM, comas y comillas) también se acepta y reemplaza al anterior
        file_put_contents($file, "\xEF\xBB\xBF".implode("\n", [
            'codigo_postal,asentamiento,tipo,municipio,estado,ciudad,zona,url',
            '06700,Roma Norte,Colonia,Cuauhtémoc,Ciudad de México,Ciudad de México,Urbano,https://example.com/06700',
            '80000,"Centro, Sector 1",Colonia,Culiacán,Sinaloa,Culiacán Rosales,Urbano,https://example.com/80000',
        ]));
        $this->artisan('sepomex:import', ['file' => $file])->assertSuccessful();
        $this->assertSame(2, PostalCode::count());
        $this->assertSame('Cuauhtémoc', PostalCode::lookup('06700')['municipality']);
        $this->assertEquals(['Centro, Sector 1'], PostalCode::lookup('80000')['settlements']->all());

        // Un archivo que no es de Correos no borra el catálogo cargado
        file_put_contents($file, "hola\nmundo");
        $this->artisan('sepomex:import', ['file' => $file])->assertFailed();
        $this->assertSame(2, PostalCode::count());
        unlink($file);
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
            'main_activity' => 'Ventas o atención al cliente',
            'pld_activity' => 'Ninguna de las anteriores',
            'seniority_working' => 3,
            'monthly_income' => 12000,
            'monthly_expenses' => 7500,
        ])->assertCreated();

        $this->assertEquals(7500, Customer::find($data['id'])->employment->monthly_expenses);
        $this->assertSame('Ninguna de las anteriores', Customer::find($data['id'])->employment->pld_activity);
    }

    public function test_the_employment_catalogs_are_enforced(): void
    {
        $data = $this->startRequest();

        $this->postJson("/request-credit/customer/{$data['id']}/employment", [
            'type' => 'Empleado formal',
            'area' => 'Astronauta',
            'main_activity' => 'Comercio',
            'seniority_working' => 3,
            'monthly_income' => 12000,
            'monthly_expenses' => 7500,
        ])->assertStatus(422)->assertJsonValidationErrors(['area', 'main_activity', 'pld_activity']);

        $this->get('/prestamo-personal/solicitar')->assertInertia(fn ($page) => $page
            ->where('catalogs.workAreas', config('catalogs.work_areas'))
            ->where('catalogs.pldActivities', config('catalogs.pld_activities'))
        );
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

    private const CLABE = '002180001234567896';

    private function references(array $override = []): array
    {
        return array_merge([
            'clabe' => self::CLABE,
            'references' => [
                ['name' => 'Carlos Ruiz', 'relationship' => 'Hermano', 'phone' => '6671112233'],
                ['name' => 'Elena Soto', 'relationship' => 'Amiga', 'phone' => '6674445566'],
            ],
        ], $override);
    }

    private function requestWithLoan(bool $withReferences = true): array
    {
        $data = $this->startRequest();
        $this->postJson("/request-credit/customer/{$data['id']}/loan", [
            'amount' => 3000, 'term' => 6, 'purpose' => 'Negocio', 'purpose_description' => 'x', 'type' => 'NORMAL',
        ])->assertCreated();
        if ($withReferences) {
            $this->postJson("/request-credit/customer/{$data['id']}/references", $this->references())->assertCreated();
        }

        return $data;
    }

    public function test_the_request_is_incomplete_until_the_documents_are_sent(): void
    {
        config(['filesystems.documents' => 'local']);
        Storage::fake('local');
        $data = $this->requestWithLoan();
        $loan = Loan::findOrFail($data['loan']['id']);

        $this->assertSame('incomplete', $loan->stage);
        $this->assertEquals(3000, $loan->requested_amount);
        $this->assertSame(6, $loan->requested_term);

        $this->post("/request-credit/customer/{$data['id']}/documents", $this->documents(), ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('received', $loan->fresh()->stage);
        $this->assertNotNull($loan->fresh()->submitted_at);
    }

    public function test_the_documents_step_requires_the_references_and_the_clabe(): void
    {
        config(['filesystems.documents' => 'local']);
        Storage::fake('local');
        $data = $this->requestWithLoan(false);

        $this->post("/request-credit/customer/{$data['id']}/documents", $this->documents(), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Primero captura tus referencias personales y tu cuenta CLABE.']);
        $this->assertSame('incomplete', Loan::find($data['loan']['id'])->stage);
    }

    public function test_the_references_and_the_clabe_are_validated_and_stored(): void
    {
        $data = $this->requestWithLoan(false);
        $url = "/request-credit/customer/{$data['id']}/references";

        $this->postJson($url, $this->references(['clabe' => '002180001234567890']))->assertStatus(422)->assertJsonValidationErrors('clabe');
        $this->postJson($url, $this->references(['clabe' => '1234']))->assertStatus(422)->assertJsonValidationErrors('clabe');
        $this->postJson($url, $this->references(['references' => [['name' => 'Solo', 'relationship' => 'Primo', 'phone' => '6671112233']]]))
            ->assertStatus(422)->assertJsonValidationErrors('references');
        $this->postJson($url, $this->references(['references' => [
            ['name' => 'Carlos', 'relationship' => 'Hermano', 'phone' => '667111'],
            ['name' => 'Elena', 'relationship' => 'Amiga', 'phone' => '6674445566'],
        ]]))->assertStatus(422)->assertJsonValidationErrors('references.0.phone');
        // El teléfono de una referencia no puede ser el del propio cliente
        $this->postJson($url, $this->references(['references' => [
            ['name' => 'Carlos', 'relationship' => 'Hermano', 'phone' => '6671234567'],
            ['name' => 'Elena', 'relationship' => 'Amiga', 'phone' => '6674445566'],
        ]]))->assertStatus(422);

        $this->postJson($url, $this->references(['clabe' => '0021 8000 1234 5678 96']))->assertCreated();
        $this->postJson($url, $this->references())->assertCreated();

        $customer = Customer::findOrFail($data['id']);
        $this->assertSame(self::CLABE, $customer->clabe);
        $this->assertSame(2, $customer->references()->count());
    }

    public function test_the_simulator_carries_the_amount_and_term_to_the_request(): void
    {
        $this->withoutVite();

        $this->get('/prestamo-personal/solicitar?monto=5000&plazo=9&tipo=NORMAL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('RequestCredit')->where('simulation', ['type' => 'NORMAL', 'amount' => 5000, 'term' => 9]));

        // Valores fuera del producto se ignoran
        $this->get('/prestamo-personal/solicitar?monto=999999&plazo=9')
            ->assertInertia(fn ($page) => $page->where('simulation', null));
        $this->get('/prestamo-personal/solicitar')
            ->assertInertia(fn ($page) => $page->where('simulation', null));
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
