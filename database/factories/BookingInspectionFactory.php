<?php

namespace Database\Factories;

use App\Models\BookingInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingInspection>
 */
class BookingInspectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => BookingInspection::TYPE_PICKUP,
            'odometer_km' => 12000,
            'fuel_percent' => 100,
            'signed_by' => fake()->name(),
            'completed_at' => now(),
        ];
    }

    public function returned(): static
    {
        return $this->state(fn () => ['type' => BookingInspection::TYPE_RETURN, 'odometer_km' => 12300, 'fuel_percent' => 75]);
    }
}
