<?php

namespace Database\Factories;

use App\Models\PmrRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PmrRecord>
 */
class PmrRecordFactory extends Factory
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
            'purity' => fake()->randomFloat(2, 80, 100),
            'mc' => fake()->randomFloat(2, 8, 20),
            'quality' => 'gqa',
            'aged_months' => fake()->numberBetween(0, 24),
            'volume_bags' => fake()->randomFloat(3, 1, 20000),
            'trial_number' => fake()->numberBetween(1, 5),
            'palay_input_kg' => 10000,
            'rice_recovery_kg' => fake()->randomFloat(2, 5000, 7000),
        ];
    }
}
