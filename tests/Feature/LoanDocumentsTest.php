<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;
use ZipArchive;

class LoanDocumentsTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.documents' => 'local']);
        Storage::fake('local');
        $this->role('Customer');
    }

    private function approve(string $type, float $amount, int $term): Loan
    {
        $customer = Customer::create([
            'status_id' => $this->customerStatus('LOAN CREATED')->id,
            'name' => 'Laura',
            'last_name' => 'Gómez',
            'gender' => 'Femenino',
            'email' => 'laura'.uniqid().'@example.com',
            'curp' => 'GOML900101MSLMRR01',
            'rfc' => 'GOML900101AB1',
        ]);
        $loan = Loan::create(['customer_id' => $customer->id, 'type' => $type, 'amount' => $amount, 'term' => $term, 'purpose' => 'Negocio']);

        $this->actingAs($this->userWithRole('Administrator'))->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();

        return $loan->fresh();
    }

    /** Texto plano de un Word generado (cada párrafo en su línea). */
    private function text(Loan $loan, string $type): string
    {
        return $this->readDocx($loan, $type, 'word/document.xml', true);
    }

    /** Texto de todos los encabezados del documento (el RECA vive ahí). */
    private function header(Loan $loan, string $type): string
    {
        $path = "loans/{$loan->id}/generated/{$type}.docx";
        $tmp = tempnam(sys_get_temp_dir(), 'dx');
        file_put_contents($tmp, Storage::disk('local')->get($path));
        $zip = new ZipArchive;
        $zip->open($tmp);

        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/header\d*\.xml$#', $name)) {
                $text .= strip_tags((string) $zip->getFromIndex($i)).' ';
            }
        }
        $zip->close();
        @unlink($tmp);

        return $text;
    }

    private function readDocx(Loan $loan, string $type, string $part, bool $plain): string
    {
        $path = "loans/{$loan->id}/generated/{$type}.docx";
        Storage::disk('local')->assertExists($path);

        $tmp = tempnam(sys_get_temp_dir(), 'dx');
        file_put_contents($tmp, Storage::disk('local')->get($path));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, "No se pudo abrir {$type}.docx");
        $xml = (string) $zip->getFromName($part);
        $zip->close();
        @unlink($tmp);

        if (! $plain) {
            return $xml;
        }

        return html_entity_decode(strip_tags(preg_replace('/<\/w:p>/', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function expectedCat(string $type, float $amount, int $term): string
    {
        $cat = app(LoanCalculator::class)->simulate($amount, $term, LoanProduct::forType($type))['cat_sin_iva'];

        return number_format($cat, 1, '.', '').'%';
    }

    // ---- Carátula (Anexo A) --------------------------------------------------------------------

    public function test_the_cover_shows_the_real_cat_of_each_loan_instead_of_a_fixed_number(): void
    {
        $loan = $this->approve('NORMAL', 3000, 9);
        $cover = $this->text($loan, 'ANEXO_A');

        $this->assertSame('134.1%', $this->expectedCat('NORMAL', 3000, 9));
        $this->assertMatchesRegularExpression('/^134\.1%$/m', $cover);
        $this->assertStringNotContainsString('124.55', $cover);
    }

    public function test_the_cat_changes_with_the_term(): void
    {
        $short = $this->text($this->approve('NORMAL', 3000, 6), 'ANEXO_A');
        $long = $this->text($this->approve('NORMAL', 3000, 12), 'ANEXO_A');

        $this->assertMatchesRegularExpression('/^141\.4%$/m', $short);
        $this->assertMatchesRegularExpression('/^130\.3%$/m', $long);
    }

    public function test_the_total_to_pay_includes_the_commission_and_its_vat(): void
    {
        $cover = $this->text($this->approve('NORMAL', 3000, 9), 'ANEXO_A');

        $this->assertMatchesRegularExpression('/^\$3,000\.00$/m', $cover);
        // 4,251.81 en cuotas + 90.00 de comisión + 14.40 de IVA de la comisión
        $this->assertMatchesRegularExpression('/^\$4,356\.21$/m', $cover);
        $this->assertStringNotContainsString('$4,251.81', $cover);
    }

    public function test_a_personal_loan_cover_does_not_mention_a_guarantee(): void
    {
        $cover = $this->text($this->approve('NORMAL', 5000, 6), 'ANEXO_A');

        $this->assertStringContainsString('CRÉDITO PERSONAL', $cover);
        $this->assertStringNotContainsString('CON GARANTÍA', $cover);
        $this->assertStringContainsString('Crédito Simple para persona física', $cover);
        $this->assertStringNotContainsString('garantía prendaria', $cover);
        $this->assertStringContainsString('ORDINARIA: 79.56%', $cover);
        $this->assertStringContainsString('6 MESES', $cover);
        $this->assertStringContainsString('Comisión por Apertura: 3 %', $cover);
    }

    public function test_a_guarantee_loan_cover_keeps_the_guarantee_wording_and_its_own_rate(): void
    {
        $loan = $this->approve('FUNERARIO', 5000, 12);
        $cover = $this->text($loan, 'ANEXO_A');

        $this->assertStringContainsString('CRÉDITO PERSONAL CON GARANTÍA', $cover);
        $this->assertStringContainsString('garantía prendaria sin transmisión de posesión.', $cover);
        $this->assertStringContainsString('ORDINARIA: 74.28%', $cover);
        $this->assertMatchesRegularExpression('/^'.preg_quote($this->expectedCat('FUNERARIO', 5000, 12), '/').'$/m', $cover);
        $this->assertStringNotContainsString('124.55', $cover);
    }

    public function test_the_cover_follows_the_rate_and_commission_configured_in_the_product(): void
    {
        LoanProduct::where('type', 'NORMAL')->update(['annual_rate' => 60, 'opening_fee' => 0]);
        $loan = $this->approve('NORMAL', 4000, 8);
        $cover = $this->text($loan, 'ANEXO_A');

        $this->assertStringContainsString('ORDINARIA: 60%', $cover);
        $this->assertStringContainsString('Comisión por Apertura: 0 %', $cover);
        $this->assertMatchesRegularExpression('/^'.preg_quote($this->expectedCat('NORMAL', 4000, 8), '/').'$/m', $cover);
    }

    // ---- Contratos -----------------------------------------------------------------------------

    public function test_the_guarantee_contract_has_the_corrected_clauses(): void
    {
        $contract = $this->text($this->approve('FUNERARIO', 5000, 12), 'CONTRATO');

        $this->assertStringNotContainsString('Municipio, Estado', $contract);
        $this->assertStringContainsString('Tribunales competentes de la Ciudad de Culiacán Rosales, Sinaloa', $contract);
        $this->assertStringNotContainsString('crédito puente', $contract);
        $this->assertStringContainsString('se adjuntan al presente contrato de crédito simple', $contract);
    }

    public function test_the_guarantee_contract_signature_has_the_client_and_the_representative(): void
    {
        $contract = $this->text($this->approve('FUNERARIO', 5000, 12), 'CONTRATO');

        $this->assertStringContainsString('Nombre: LAURA GÓMEZ', $contract);
        $this->assertStringContainsString('Lic. ELIA DE JESÚS OCHOA TIRADO', $contract);
        $this->assertStringNotContainsString('Nombre del cliente', $contract);
        $this->assertStringNotContainsString('[Nombre del Representante]', $contract);
        $this->assertStringContainsString('PROMÁS, S.A.P.I.', $contract);
        $this->assertStringContainsString('Culiacán, Sinaloa, a ', $contract);
    }

    public function test_the_personal_contract_is_filled_in_and_has_the_corrected_clauses(): void
    {
        $contract = $this->text($this->approve('NORMAL', 3000, 9), 'CONTRATO');

        $this->assertStringContainsString('LAURA GÓMEZ', $contract);
        $this->assertStringContainsString('79.56%', $contract);
        $this->assertStringNotContainsString('Nombre del cliente', $contract);
        $this->assertStringNotContainsString('[Nombre del Representante]', $contract);
        $this->assertStringNotContainsString('crédito puente', $contract);
        $this->assertStringNotContainsString('Municipio, Estado', $contract);
        $this->assertStringContainsString('Tribunales competentes de la Ciudad de Culiacán Rosales, Sinaloa', $contract);
    }

    // ---- Anexos por producto --------------------------------------------------------------------

    public function test_each_product_uses_its_own_annexes_with_their_reca(): void
    {
        $expected = ['NORMAL' => '17405-140-044349/01-02081-0826', 'FUNERARIO' => '17405-140-044657/01-02617-1026'];

        foreach ($expected as $type => $reca) {
            $loan = $this->approve($type, 3000, 9);

            foreach (['CONTRATO', 'ANEXO_A', 'ANEXO_B', 'ANEXO_C', 'ANEXO_D'] as $doc) {
                $this->assertStringContainsString($reca, $this->header($loan, $doc), "{$doc} ({$type}) debe traer su RECA");
            }

            foreach (['ANEXO_B', 'ANEXO_C', 'ANEXO_D'] as $annex) {
                $this->assertStringContainsString('LAURA GÓMEZ', $this->text($loan, $annex));
            }
        }
    }

    public function test_every_product_folder_has_its_contract_and_four_annexes(): void
    {
        foreach (['con-garantia', 'sin-garantia'] as $dir) {
            $this->assertCount(5, glob(base_path("document/{$dir}/*.docx")), $dir);
            $this->assertCount(4, glob(base_path("document/{$dir}/ANEXO *.docx")), $dir);
        }
        $this->assertDirectoryDoesNotExist(base_path('document/anexos'));
    }

    public function test_every_generated_word_file_is_a_valid_document(): void
    {
        $loan = $this->approve('FUNERARIO', 3000, 9);

        foreach (['CONTRATO', 'ANEXO_A', 'ANEXO_B', 'ANEXO_C', 'ANEXO_D'] as $type) {
            $xml = $this->readDocx($loan, $type, 'word/document.xml', false);
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xml), "El XML de {$type} no es válido");
        }
    }
}
