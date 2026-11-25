<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Models\VehicleInventory;
use App\Services\Fleet\MaintenanceBlocker;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:extend {--days=180 : How many days ahead vehicles should be open for rental}')]
#[Description('Open further days for every vehicle, repeating the units and prices of the week before; existing days and bookings are never changed')]
class ExtendInventory extends Command
{
    public function handle(MaintenanceBlocker $maintenance): int
    {
        $days = max(1, (int) $this->option('days'));
        $added = 0;

        Vehicle::query()->where('is_active', true)->with('rental')->each(function (Vehicle $vehicle) use ($days, $maintenance, &$added): void {
            $opened = $this->extend($vehicle, $days);
            $added += $opened;

            if ($opened > 0) {
                // A service or repair that runs past the old horizon must also block the new days.
                $maintenance->sync($vehicle);
            }
        });

        $this->info("Opened {$added} day(s).");

        return self::SUCCESS;
    }

    private function extend(Vehicle $vehicle, int $days): int
    {
        $last = VehicleInventory::query()->where('vehicle_id', $vehicle->id)->orderByDesc('rental_date')->first();

        if (! $last) {
            return 0;
        }

        $horizon = CarbonImmutable::now($vehicle->rental->timezone)->startOfDay()->addDays($days);
        $week = VehicleInventory::query()->where('vehicle_id', $vehicle->id)
            ->whereDate('rental_date', '>', $last->rental_date->copy()->subDays(7)->toDateString())
            ->get()
            ->keyBy(fn (VehicleInventory $day) => $day->rental_date->toDateString())
            ->map(fn (VehicleInventory $day) => ['total_units' => $day->total_units, 'price' => (float) $day->price])
            ->all();

        $rows = [];
        for ($date = CarbonImmutable::parse($last->rental_date)->addDay(); $date->lte($horizon); $date = $date->addDay()) {
            $template = $week[$date->subDays(7)->toDateString()] ?? ['total_units' => $last->total_units, 'price' => (float) $last->price];
            $week[$date->toDateString()] = $template;

            $rows[] = [
                'vehicle_id' => $vehicle->id,
                'rental_date' => $date->startOfDay()->toDateTimeString(),
                'total_units' => $template['total_units'],
                'booked_units' => 0,
                'blocked_units' => 0,
                'price' => $template['price'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $inserted = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $inserted += VehicleInventory::query()->insertOrIgnore($chunk);
        }

        return $inserted;
    }
}
