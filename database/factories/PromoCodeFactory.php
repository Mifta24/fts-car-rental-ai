<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('????##')),
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'min_days' => 1,
            'is_active' => true,
        ];
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn () => ['discount_type' => PromoCode::TYPE_FIXED, 'discount_value' => $amount]);
    }
}
