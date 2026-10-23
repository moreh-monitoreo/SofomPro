<?php

namespace Tests\Feature;

use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class LoanProductsAdminTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    private function valid(array $override = []): array
    {
        return array_merge([
            'name' => 'Préstamo personal',
            'annual_rate' => 70,
            'opening_fee' => 2.5,
            'iva' => 16,
            'min_amount' => 1000,
            'max_amount' => 15000,
            'amount_step' => 500,
            'min_term' => 3,
            'max_term' => 24,
            'active' => true,
        ], $override);
    }

    public function test_only_administrators_can_see_the_products_page(): void
    {
        $this->get('/admin/loan-products')->assertRedirect('/login');
        $this->actingAs($this->userWithRole('Customer'))->get('/admin/loan-products')->assertForbidden();
        $this->actingAs($this->userWithRole('Advisor'))->get('/admin/loan-products')->assertForbidden();
        $this->actingAs($this->userWithRole('Administrator'))->get('/admin/loan-products')->assertOk();
    }

    public function test_only_administrators_can_change_a_product(): void
    {
        $this->put('/admin/loan-products/NORMAL', $this->valid())->assertRedirect('/login');
        $this->actingAs($this->userWithRole('Advisor'))->put('/admin/loan-products/NORMAL', $this->valid())->assertForbidden();
        $this->actingAs($this->userWithRole('Customer'))->put('/admin/loan-products/NORMAL', $this->valid())->assertForbidden();

        $this->assertEquals(79.56, LoanProduct::where('type', 'NORMAL')->value('annual_rate'));
    }

    public function test_an_administrator_updates_the_product_and_the_simulator_follows(): void
    {
        $admin = $this->userWithRole('Administrator');

        $this->actingAs($admin)->put('/admin/loan-products/NORMAL', $this->valid())->assertRedirect()->assertSessionHasNoErrors();

        $product = LoanProduct::where('type', 'NORMAL')->firstOrFail();
        $this->assertEquals(70, $product->annual_rate);
        $this->assertSame(24, $product->max_term);

        $this->postJson('/api/simulator', ['amountCredit' => 14500, 'quantityPayments' => 24, 'type' => 'NORMAL'])
            ->assertOk()->assertJsonPath('annual_rate', 70)->assertJsonPath('opening_fee_percent', 2.5);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $admin = $this->userWithRole('Administrator');

        $this->actingAs($admin)->put('/admin/loan-products/NORMAL', $this->valid(['annual_rate' => 0, 'max_amount' => 500, 'max_term' => 1]))
            ->assertSessionHasErrors(['annual_rate', 'max_amount', 'max_term']);

        $this->actingAs($admin)->put('/admin/loan-products/NORMAL', $this->valid(['amount_step' => 600]))
            ->assertSessionHasErrors('amount_step');

        $this->actingAs($admin)->put('/admin/loan-products/OTRO', $this->valid())->assertNotFound();

        $this->assertEquals(79.56, LoanProduct::where('type', 'NORMAL')->value('annual_rate'));
    }

    public function test_an_inactive_product_disappears_from_the_simulator(): void
    {
        $this->actingAs($this->userWithRole('Administrator'))
            ->put('/admin/loan-products/FUNERARIO', $this->valid(['name' => 'Garantía', 'active' => false]))->assertSessionHasNoErrors();

        $config = $this->getJson('/api/simulator/config')->assertOk();
        $config->assertJsonMissingPath('products.FUNERARIO');
        $config->assertJsonPath('products.NORMAL.type', 'NORMAL');

        $this->postJson('/api/simulator', ['amountCredit' => 5000, 'quantityPayments' => 6, 'type' => 'FUNERARIO'])
            ->assertStatus(422)->assertJsonValidationErrors('type');
    }
}
