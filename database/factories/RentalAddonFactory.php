<?php

namespace Database\Factories;

use App\Models\RentalAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalAddon>
 */
class RentalAddonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => 50000,
            'charge' => RentalAddon::CHARGE_PER_BOOKING,
            'is_active' => true,
        ];
    }

    public function perDay(): static
    {
        return $this->state(fn () => ['charge' => RentalAddon::CHARGE_PER_DAY]);
    }
}
