<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status_id' => 1,
            'name' => fake()->name(),
            'second_name' => fake()->name(),
            'last_name' => fake()->name(),
            'maiden_name' => fake()->name(),
            'gender' => fake()->randomElement(['male', 'female']),
            'birthday' => fake()->dateTimeBetween('-100 years', '-18 years'),
            'state' => fake()->state(),
            'country' => fake()->country(),
            'rfc' => fake()->unique()->bothify('??-########-#'),
            'curp' => fake()->unique()->bothify('??-########-#'),
            'civil_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'level_study' => fake()->randomElement(['primary', 'secondary', 'highschool', 'college', 'university']),
            'own_car' => fake()->randomElement(['yes', 'no']),
            'economic_dependents' => fake()->randomNumber(2),
        ];
    }
}
