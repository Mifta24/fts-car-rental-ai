<?php

namespace Database\Factories;

use App\Models\DurationDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DurationDiscount>
 */
class DurationDiscountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'min_days' => 3,
            'percent' => 5,
        ];
    }
}
