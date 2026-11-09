<?php

namespace Tests\Feature;

use App\Models\LoanPayment;
use App\Services\PortfolioReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Cuotas del préstamo de prueba: 5 sep, 5 oct y 5 nov de 2026
        $this->travelTo(Carbon::parse('2026-10-21 10:00'));
    }

    public function test_only_management_can_see_the_reports(): void
    {
        $this->actingAs($this->userWithRole('Customer'))->get('/reports')->assertForbidden();
        $this->actingAs($this->userWithRole('Advisor'))->get('/reports')->assertForbidden();
        $this->actingAs($this->userWithRole('Analista de crédito'))->get('/reports')->assertForbidden();
        $this->actingAs($this->userWithRole('Gerente'))->get('/reports')->assertOk();
        $this->actingAs($this->userWithRole('Administrator'))->get('/reports')->assertOk();
    }

    public function test_placement_counts_the_loans_of_each_month(): void
    {
        $this->approvedLoan();
        $second = $this->approvedLoan($this->userWithRole('Customer'))['loan'];
        $second->update(['amount' => 5000, 'disbursed_at' => '2026-10-02 12:00:00']);

        $placement = app(PortfolioReport::class)->placement(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-31'));

        $this->assertSame(2, $placement['count']);
        $this->assertEquals(8000, $placement['amount']);
        $this->assertEquals(4000, $placement['average']);
        $this->assertSame(['2026-09', '2026-10'], array_column($placement['months'], 'month'));
        // Sin desembolso registrado cuenta desde la aprobación (21 sep); con desembolso, desde esa fecha
        $this->assertEquals([3000, 5000], array_column($placement['months'], 'amount'));
    }

    public function test_aging_puts_each_loan_in_its_bucket_and_computes_the_delinquency_index(): void
    {
        $late = $this->approvedLoan()['loan'];
        $current = $this->approvedLoan($this->userWithRole('Customer'));
        // El segundo préstamo ya pagó las cuotas vencidas: queda al corriente
        $current['details']->take(2)->each(fn ($d) => $d->update([
            'status' => 'S', 'payed_at' => now(), 'principal_paid' => 1000, 'interest_paid' => 160,
        ]));

        $aging = app(PortfolioReport::class)->aging();
        $buckets = collect($aging['buckets'])->keyBy('key');

        // La cuota del 5 de septiembre lleva 46 días vencida
        $this->assertSame(1, $buckets['31_60']['count']);
        $this->assertEquals(3000, $buckets['31_60']['balance']);
        $this->assertSame(1, $buckets['current']['count']);
        $this->assertEquals(1000, $buckets['current']['balance']);

        $this->assertEquals(4000, $aging['delinquency']['portfolio_balance']);
        $this->assertEquals(3000, $aging['delinquency']['overdue_balance']);
        $this->assertEquals(75.0, $aging['delinquency']['index']);
        $this->assertEquals(0.0, $aging['delinquency']['index_90']);
        $this->assertSame($late->id, $aging['rows']->first()['loan_id']);
    }

    public function test_collections_compare_due_installments_with_payments_received(): void
    {
        $loan = $this->approvedLoan()['loan'];
        LoanPayment::create(['loan_id' => $loan->id, 'kind' => 'adelantado', 'amount' => 700, 'method' => 'EFECTIVO', 'allocations' => [], 'snapshot' => [], 'paid_at' => '2026-10-10 09:00:00']);
        LoanPayment::create(['loan_id' => $loan->id, 'kind' => 'adelantado', 'amount' => 999, 'method' => 'EFECTIVO', 'allocations' => [], 'snapshot' => [], 'paid_at' => '2026-10-11 09:00:00', 'reverted_at' => now()]);

        $collections = app(PortfolioReport::class)->collections(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-31'));

        $this->assertEquals([1160, 1160], array_column($collections['months'], 'expected'));
        $this->assertEquals([0, 700], array_column($collections['months'], 'collected'));
        $this->assertEquals(2320, $collections['expected']);
        $this->assertEquals(700, $collections['collected']);
        $this->assertEquals(30.17, $collections['efficiency']);
    }

    public function test_the_aging_detail_can_be_downloaded(): void
    {
        $loan = $this->approvedLoan()['loan'];

        $response = $this->actingAs($this->userWithRole('Gerente'))->get('/reports/aging/export')->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Saldo de capital', $csv);
        $this->assertStringContainsString((string) $loan->id, $csv);
        $this->assertStringContainsString('31 a 60 días', $csv);
    }

    public function test_the_period_filter_is_validated(): void
    {
        $this->actingAs($this->userWithRole('Administrator'))
            ->get('/reports?from=2026-10&to=2026-01')
            ->assertSessionHasErrors('to');
    }
}
