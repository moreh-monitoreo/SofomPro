<?php

namespace Tests\Feature;

use App\Mail\OverdueReport;
use App\Models\Customer;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
    }

    private function loan(string $name, array $attrs = [], array $customer = []): Loan
    {
        $c = Customer::create($customer + [
            'status_id' => $this->customerStatus('LOAN CREATED')->id,
            'name' => $name,
            'last_name' => 'Prueba',
            'gender' => 'Femenino',
            'curp' => strtoupper(substr(md5($name), 0, 18)),
        ]);

        return Loan::create($attrs + ['customer_id' => $c->id, 'type' => 'NORMAL', 'amount' => 3000, 'term' => 6, 'submitted_at' => now()]);
    }

    /** Una solicitud en cada etapa, más una con mora. */
    private function seedPipeline(): array
    {
        $received = $this->loan('Rebeca');
        $validation = $this->loan('Valentina', ['validation_started_at' => now()]);
        $approved = $this->loan('Adriana', ['approved_at' => now()]);
        $disbursed = $this->loan('Dolores', ['approved_at' => now(), 'disbursed_at' => now()], ['phone' => '6671112233']);
        $paid = $this->loan('Paola', ['approved_at' => now(), 'disbursed_at' => now(), 'paid_at' => now()]);
        $rejected = $this->loan('Rosa', ['reject_at' => now()], []);
        $incomplete = $this->loan('Irene', ['submitted_at' => null]);
        $validated = $this->loan('Vera', ['validation_started_at' => now(), 'validated_at' => now()]);
        $guarantee = $this->loan('Gloria', ['type' => 'FUNERARIO']);
        $overdue = $this->approvedLoan()['loan'];
        $overdue->forceFill(['disbursed_at' => now()])->save();

        return compact('received', 'validation', 'approved', 'disbursed', 'paid', 'rejected', 'guarantee', 'overdue', 'incomplete', 'validated');
    }

    private function page(array $query = [], string $role = 'Advisor')
    {
        return $this->actingAs($this->userWithRole($role))
            ->get('/pipeline?'.http_build_query($query));
    }

    public function test_the_counts_cover_every_stage(): void
    {
        $this->seedPipeline();

        $this->page()->assertInertia(fn (Assert $page) => $page
            ->component('Pipeline')
            ->where('counts.incomplete', 1)
            ->where('counts.received', 2)
            ->where('counts.in_validation', 1)
            ->where('counts.validated', 1)
            ->where('counts.approved', 1)
            ->where('counts.disbursed', 2)
            ->where('counts.paid', 1)
            ->where('counts.rejected', 1));
    }

    public function test_filtering_by_stage_only_returns_that_stage(): void
    {
        $this->seedPipeline();

        $this->page(['stage' => 'in_validation'])->assertInertia(fn (Assert $page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.customer.full_name', 'Valentina Prueba')
            ->where('loans.data.0.stage_label', 'En validación'));
    }

    public function test_the_search_matches_names_and_phones(): void
    {
        $this->seedPipeline();

        $this->page(['q' => 'adrian'])->assertInertia(fn (Assert $page) => $page->has('loans.data', 1)->where('loans.data.0.customer.full_name', 'Adriana Prueba'));
        $this->page(['q' => '6671112233'])->assertInertia(fn (Assert $page) => $page->has('loans.data', 1)->where('loans.data.0.customer.full_name', 'Dolores Prueba'));
        $this->page(['q' => 'nadie-se-llama-asi'])->assertInertia(fn (Assert $page) => $page->has('loans.data', 0));
    }

    public function test_a_search_with_wildcard_characters_does_not_match_everything(): void
    {
        $this->seedPipeline();

        $this->page(['q' => '%'])->assertInertia(fn (Assert $page) => $page->has('loans.data', 0));
    }

    public function test_filtering_by_product_and_by_date(): void
    {
        $this->seedPipeline();

        $this->page(['type' => 'FUNERARIO'])->assertInertia(fn (Assert $page) => $page->has('loans.data', 1)->where('loans.data.0.customer.full_name', 'Gloria Prueba'));

        $this->page(['from' => '2027-01-01'])->assertInertia(fn (Assert $page) => $page->has('loans.data', 0));
        $this->page(['from' => '2026-10-01', 'to' => '2026-10-31'])->assertInertia(fn (Assert $page) => $page->has('loans.data', 10));
    }

    public function test_the_overdue_filter_shows_only_loans_with_arrears(): void
    {
        $built = $this->seedPipeline();

        $this->page(['overdue' => 1])->assertInertia(fn (Assert $page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $built['overdue']->id)
            ->where('loans.data.0.is_overdue', true)
            ->where('loans.data.0.days_overdue', 46)
            ->where('loans.data.0.overdue_amount', 2320));
    }

    public function test_the_board_view_returns_every_loan_for_the_columns(): void
    {
        $this->seedPipeline();

        $this->page(['view' => 'board'])->assertInertia(fn (Assert $page) => $page
            ->component('Pipeline')
            ->has('loans', 10)
            ->where('filters.view', 'board'));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->page(['stage' => 'inventada'])->assertStatus(302);
    }

    public function test_customers_cannot_see_the_pipeline_or_the_overdue_report(): void
    {
        $customer = $this->userWithRole('Customer');

        $this->actingAs($customer)->get('/pipeline')->assertForbidden();
        $this->actingAs($customer)->get('/portfolio/overdue')->assertForbidden();
        $this->actingAs($customer)->get('/portfolio/overdue/export')->assertForbidden();
    }

    // ---- Cartera vencida -------------------------------------------------------------------

    public function test_the_overdue_report_lists_clients_with_arrears_ordered_by_delay(): void
    {
        $built = $this->seedPipeline();
        $second = $this->approvedLoan();
        $second['details'][0]->forceFill(['due_date' => '2026-10-15'])->save();
        $second['details'][1]->forceFill(['due_date' => '2026-12-05'])->save();

        $this->actingAs($this->userWithRole('Advisor'))
            ->get('/portfolio/overdue')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Overdue')
                ->has('rows', 2)
                ->where('rows.0.loan_id', $built['overdue']->id)
                ->where('rows.0.days_overdue', 46)
                ->where('rows.1.days_overdue', 6)
                ->where('totals.clients', 2)
                ->where('totals.installments', 3)
                ->where('totals.amount', 3480));
    }

    public function test_the_overdue_report_can_be_exported_as_csv(): void
    {
        $this->seedPipeline();

        $response = $this->actingAs($this->userWithRole('Administrator'))->get('/portfolio/overdue/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Cliente,Teléfono,Correo', $csv);
        $this->assertStringContainsString('2320.00', $csv);
        $this->assertStringContainsString(',46,', $csv);
    }

    public function test_the_daily_command_emails_the_team_only_when_there_is_arrears(): void
    {
        Mail::fake();
        config(['promas.notification_email' => 'equipo@promas.test']);

        $this->artisan('loans:overdue-report')->expectsOutputToContain('No hay cartera vencida')->assertSuccessful();
        Mail::assertNothingSent();

        $this->approvedLoan();
        $this->artisan('loans:overdue-report')->expectsOutputToContain('1 cliente(s)')->assertSuccessful();

        Mail::assertSent(OverdueReport::class, fn ($mail) => $mail->hasTo('equipo@promas.test') && $mail->rows->count() === 1);
    }

    public function test_the_command_fails_clearly_without_a_recipient(): void
    {
        Mail::fake();
        config(['promas.notification_email' => null]);
        $this->approvedLoan();

        $this->artisan('loans:overdue-report')->expectsOutputToContain('No hay destinatario')->assertFailed();
        Mail::assertNothingSent();
    }

    public function test_the_overdue_email_renders(): void
    {
        $this->approvedLoan();
        $rows = app(\App\Http\Controllers\OverdueController::class)->rows(app(\App\Services\LoanPortfolio::class));

        $html = (new OverdueReport($rows))->render();

        $this->assertStringContainsString('Cartera vencida', $html);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('2,320.00', $html);
    }
}
