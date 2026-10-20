<?php

namespace Database\Factories;

use App\Models\VehicleMaintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleMaintenance>
 */
class VehicleMaintenanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => VehicleMaintenance::TYPE_SERVICE,
            'title' => fake()->words(2, true),
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(6)->toDateString(),
        ];
    }

    public function expiring(string $type, string $on): static
    {
        return $this->state(fn () => ['type' => $type, 'starts_on' => null, 'ends_on' => null, 'expires_on' => $on]);
    }
}
