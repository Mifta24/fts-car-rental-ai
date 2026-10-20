<?php

namespace App\Services\Fleet;

use App\Models\Vehicle;
use App\Models\VehicleInventory;
use App\Models\VehicleMaintenance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps `vehicle_inventory.blocked_units` equal to the number of open service or
 * repair records covering each day, so a car in the workshop cannot be quoted or
 * booked. It recomputes from the records, so it is safe to call at any time.
 */
class MaintenanceBlocker
{
    public function sync(Vehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle) {
            VehicleInventory::where('vehicle_id', $vehicle->id)->where('blocked_units', '>', 0)->update(['blocked_units' => 0]);

            VehicleMaintenance::where('vehicle_id', $vehicle->id)
                ->whereIn('type', VehicleMaintenance::BLOCKING_TYPES)
                ->whereNull('completed_at')
                ->get()
                ->each(fn (VehicleMaintenance $record) => VehicleInventory::where('vehicle_id', $vehicle->id)
                    ->whereDate('rental_date', '>=', $record->starts_on->toDateString())
                    ->whereDate('rental_date', '<=', $record->ends_on->toDateString())
                    ->increment('blocked_units'));
        });
    }

    /**
     * Days in the range where taking one more unit out of service would cancel
     * out a unit that is already promised to a customer.
     *
     * @return Collection<int, string>
     */
    public function conflictingDates(Vehicle $vehicle, CarbonImmutable $start, CarbonImmutable $end, ?VehicleMaintenance $replacing = null): Collection
    {
        return VehicleInventory::where('vehicle_id', $vehicle->id)
            ->whereDate('rental_date', '>=', $start->toDateString())
            ->whereDate('rental_date', '<=', $end->toDateString())
            ->where('booked_units', '>', 0)
            ->orderBy('rental_date')
            ->get()
            ->filter(function (VehicleInventory $day) use ($replacing) {
                $blocked = $day->blocked_units;

                if ($replacing?->isActiveBlock()
                    && $day->rental_date->between($replacing->starts_on, $replacing->ends_on)) {
                    $blocked--;
                }

                return $day->total_units - $day->booked_units - $blocked < 1;
            })
            ->map(fn (VehicleInventory $day) => $day->rental_date->toDateString())
            ->values();
    }
}
