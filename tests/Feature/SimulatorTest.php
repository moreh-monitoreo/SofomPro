<?php

namespace Tests\Feature;

use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatorTest extends TestCase
{
    use RefreshDatabase;

    private function simulate(array $override = [])
    {
        return $this->postJson('/api/simulator', array_merge(['amountCredit' => 5000, 'quantityPayments' => 6, 'type' => 'NORMAL'], $override));
    }

    public function test_the_simulation_returns_the_schedule_and_both_cat_values(): void
    {
        $response = $this->simulate()->assertOk()->assertJsonStructure([
            'annual_rate', 'monthly_rate', 'iva_rate', 'opening_fee_percent', 'cat_sin_iva', 'cat_con_iva',
            'first_payment', 'total_to_pay', 'schedule', 'totals', 'payment', 'calculated_at',
        ]);

        $data = $response->json();
        $this->assertCount(6, $data['schedule']);
        $this->assertEquals(79.56, $data['annual_rate']);
        $this->assertGreaterThan(0, $data['cat_sin_iva']);
        $this->assertGreaterThan($data['cat_sin_iva'], $data['cat_con_iva']);
    }

    public function test_the_schedule_adds_up(): void
    {
        $data = $this->simulate()->json();
        $schedule = collect($data['schedule']);

        $this->assertEqualsWithDelta(5000, $schedule->sum('principal'), 0.05);
        $this->assertEquals(0, $schedule->last()['balance']);
        $this->assertEquals(5000, $schedule->first()['opening_balance']);

        foreach ($schedule as $row) {
            $this->assertEqualsWithDelta($row['principal'] + $row['interest'] + $row['iva'], $row['payment'], 0.02);
            $this->assertEqualsWithDelta($row['interest'] * 0.16, $row['iva'], 0.02);
        }

        $this->assertEqualsWithDelta($schedule->sum('payment'), $data['totals']['payment'], 0.02);
        $this->assertEqualsWithDelta($data['totals']['payment'] + $data['opening_fee_amount'] + $data['opening_fee_iva'], $data['total_to_pay'], 0.02);
    }

    public function test_the_reported_cat_is_consistent_with_the_cash_flows(): void
    {
        $data = $this->simulate(['amountCredit' => 3000])->json();

        $net = 3000 - $data['opening_fee_amount'] - $data['opening_fee_iva'];
        $monthly = (1 + $data['cat_con_iva'] / 100) ** (1 / 12) - 1;
        $presentValue = 0.0;
        foreach ($data['schedule'] as $i => $row) {
            $presentValue += $row['payment'] / (1 + $monthly) ** ($i + 1);
        }

        $this->assertEqualsWithDelta($net, $presentValue, $net * 0.003);
    }

    public function test_the_guarantee_product_uses_its_own_rate(): void
    {
        $this->assertEquals(74.28, $this->simulate(['type' => 'FUNERARIO'])->json('annual_rate'));
    }

    public function test_amounts_and_terms_are_limited_by_the_product(): void
    {
        $this->simulate(['amountCredit' => 1000])->assertStatus(422)->assertJsonValidationErrors('amountCredit');
        $this->simulate(['amountCredit' => 50000])->assertStatus(422)->assertJsonValidationErrors('amountCredit');
        $this->simulate(['amountCredit' => 5500])->assertStatus(422)->assertJsonValidationErrors('amountCredit');
        $this->simulate(['quantityPayments' => 3])->assertStatus(422)->assertJsonValidationErrors('quantityPayments');
        $this->simulate(['quantityPayments' => 24])->assertStatus(422)->assertJsonValidationErrors('quantityPayments');
    }

    public function test_changing_the_product_in_the_database_changes_the_simulation_without_a_deploy(): void
    {
        $before = $this->simulate()->json();

        LoanProduct::where('type', 'NORMAL')->update(['annual_rate' => 60, 'max_amount' => 20000, 'max_term' => 18, 'opening_fee' => 0]);

        $after = $this->simulate(['amountCredit' => 15000, 'quantityPayments' => 18])->assertOk()->json();

        $this->assertEquals(60, $after['annual_rate']);
        $this->assertEquals(0, $after['opening_fee_amount']);
        $this->assertCount(18, $after['schedule']);
        $this->assertLessThan($before['monthly_rate'], $after['monthly_rate']);
    }

    public function test_the_simulator_still_works_if_the_products_table_is_empty(): void
    {
        LoanProduct::query()->delete();

        $this->simulate()->assertOk()->assertJsonPath('annual_rate', 79.56);
    }

    public function test_the_config_endpoint_exposes_the_current_limits(): void
    {
        LoanProduct::where('type', 'NORMAL')->update(['min_amount' => 3000]);

        $this->getJson('/api/simulator/config')->assertOk()
            ->assertJsonPath('products.NORMAL.min_amount', 3000)
            ->assertJsonPath('products.FUNERARIO.annual_rate', 74.28)
            ->assertJsonStructure(['products' => ['NORMAL' => ['name', 'annual_rate', 'iva', 'opening_fee', 'min_amount', 'max_amount', 'amount_step', 'min_term', 'max_term']]]);
    }
}
