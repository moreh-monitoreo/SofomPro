<?php

namespace Tests\Unit;

use App\Support\Cat;
use PHPUnit\Framework\TestCase;

class CatTest extends TestCase
{
    public function test_a_loan_without_fees_has_the_compounded_interest_rate_as_cat(): void
    {
        // 12 % anual = 1 % mensual; cuota fija de un crédito de $10,000 a 12 meses
        $payment = 10000 * 0.01 / (1 - 1.01 ** -12);

        $this->assertEqualsWithDelta(12.7, Cat::annual(10000, array_fill(0, 12, $payment)), 0.05);
    }

    public function test_upfront_fees_raise_the_cat(): void
    {
        $payment = 10000 * 0.01 / (1 - 1.01 ** -12);
        $payments = array_fill(0, 12, $payment);

        $this->assertGreaterThan(Cat::annual(10000, $payments), Cat::annual(9700, $payments));
    }

    public function test_the_cat_makes_the_present_value_of_the_payments_equal_the_net_disbursement(): void
    {
        $net = 2910.0;
        $payments = [560.5, 551.2, 541.9, 532.6, 523.3, 514.0];

        $cat = Cat::annual($net, $payments);
        $monthly = (1 + $cat / 100) ** (1 / 12) - 1;
        $presentValue = 0.0;
        foreach ($payments as $i => $p) {
            $presentValue += $p / (1 + $monthly) ** ($i + 1);
        }

        $this->assertEqualsWithDelta($net, $presentValue, $net * 0.002);
    }

    public function test_the_cat_is_zero_when_there_is_nothing_to_compute(): void
    {
        $this->assertSame(0.0, Cat::annual(0, [100.0]));
        $this->assertSame(0.0, Cat::annual(1000, []));
        $this->assertSame(0.0, Cat::annual(1000, [500.0, 500.0]));
    }
}
