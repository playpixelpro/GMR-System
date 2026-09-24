<?php

namespace Database\Factories;

use App\Models\AmrRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AmrRecord>
 */
class AmrRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_name' => fake()->company(),
            'pile_number' => (string) fake()->numberBetween(1, 50),
            'variety' => fake()->randomElement(['PD', 'NSIC Rc 222']),
            'aged_months' => fake()->numberBetween(0, 24),
            'volume_bags' => fake()->randomFloat(3, 1, 20000),
            'rice_millers' => fake()->company(),
            'trial_number' => fake()->numberBetween(1, 3),
            'palay_input_kg' => fake()->randomFloat(2, 1, 10000),
            'rice_recovery_kg' => fake()->randomFloat(2, 1, 7000),
        ];
    }
}
