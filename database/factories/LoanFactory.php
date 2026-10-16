<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Customer;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['personal', 'business']),
            'policy_folio' => fake()->bothify('##########'),
            'customer_id' => Customer::factory(),
            'amount' => fake()->randomFloat(2, 1000, 10000),
            'purpose' => fake()->randomElement(['personal', 'business']),
            'purpose_description' => fake()->sentence(),
            'term' => fake()->randomNumber(2),
            'score' => fake()->randomNumber(2),
            'approved_at' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'reject_at' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'start_at' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'paid_at' => fake()->dateTimeBetween('-1 year', '+1 year'),
        ];
    }
}
