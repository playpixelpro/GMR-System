<?php

namespace Database\Factories;

use App\Models\Miller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Miller>
 */
class MillerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lastName().' Rice Mill',
            'category' => fake()->randomElement(['nfa_owned', 'private']),
            'capacity_12h_bags' => fake()->randomFloat(3, 100, 5000),
        ];
    }

    public function nfaOwned(): static
    {
        return $this->state(fn (): array => ['category' => 'nfa_owned']);
    }

    public function private(): static
    {
        return $this->state(fn (): array => ['category' => 'private']);
    }
}
