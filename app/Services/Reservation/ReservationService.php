<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The single place where trip prices, availability and reservation requests
 * are computed. Both the AI agent tools and the customer-facing reservation
 * wizard go through here, so they can never disagree.
 */
class ReservationService
{
    public const MAX_DAYS = 30;

    /**
     * How long an unanswered request keeps its cars off the market before the
     * units go back to the inventory.
     */
    public const PENDING_HOLD_HOURS = 48;

    /**
     * Whether the requested party fits the vehicle across the requested units.
     */
    public function fitsPassengers(Vehicle $vehicle, int $passengers, int $units): bool
    {
        return $passengers <= $vehicle->maxPassengers() * $units;
    }

    /**
     * Prices a trip across every day in range. Returns null if any day
     * lacks enough free units — this is the one place price and availability
     * truth comes from, never the model.
     *
     * @return array{days: int, daily: list<array{date: string, price: float}>, vehicle_total: float, driver_total: float, grand_total: float, min_available_units: int}|null
     */
    public function quote(
        Vehicle $vehicle,
        CarbonImmutable $pickupDate,
        CarbonImmutable $returnDate,
        int $units = 1,
        bool $withDriver = false,
        bool $lockForUpdate = false,
    ): ?array {
        $days = $pickupDate->diffInDays($returnDate);

        if ($days < 1) {
            return null;
        }

        $query = VehicleInventory::where('vehicle_id', $vehicle->id)
            ->whereDate('rental_date', '>=', $pickupDate->toDateString())
            ->whereDate('rental_date', '<=', $returnDate->subDay()->toDateString())
            ->orderBy('rental_date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $inventory = $query->get();

        if ($inventory->count() < $days) {
            return null;
        }

        $daily = [];
        $perVehicleTotal = 0.0;
        $minAvailable = PHP_INT_MAX;

        foreach ($inventory as $day) {
            if ($day->availableUnits() < $units) {
                return null;
            }

            $daily[] = ['date' => $day->rental_date->toDateString(), 'price' => (float) $day->price];
            $perVehicleTotal += (float) $day->price;
            $minAvailable = min($minAvailable, $day->availableUnits());
        }

        $vehicleTotal = $perVehicleTotal * $units;
        $driverTotal = $withDriver && $vehicle->driver_available
            ? (float) $vehicle->driver_price * $days * $units
            : 0.0;

        return [
            'days' => $days,
            'daily' => $daily,
            'vehicle_total' => $vehicleTotal,
            'driver_total' => $driverTotal,
            'grand_total' => $vehicleTotal + $driverTotal,
            'min_available_units' => $minAvailable,
        ];
    }

    /**
     * Creates a pending reservation request and holds the inventory for it.
     * Returns null when the vehicle is no longer available for those days.
     *
     * @param  array{pickup_date: CarbonImmutable, return_date: CarbonImmutable, passengers: int, units?: int, with_driver?: bool, customer_name: string, customer_email?: ?string, customer_phone?: ?string, contact_type?: ?string, notes?: ?string}  $data
     */
    public function createRequest(Rental $rental, Vehicle $vehicle, array $data, ?Conversation $conversation = null): ?Booking
    {
        $units = $data['units'] ?? 1;
        $withDriver = (bool) ($data['with_driver'] ?? false);

        return DB::transaction(function () use ($rental, $vehicle, $data, $conversation, $units, $withDriver) {
            $quote = $this->quote($vehicle, $data['pickup_date'], $data['return_date'], $units, $withDriver, lockForUpdate: true);

            if (! $quote) {
                return null;
            }

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'rental_id' => $rental->id,
                'vehicle_id' => $vehicle->id,
                'conversation_id' => $conversation?->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'contact_type' => $data['contact_type'] ?? null,
                'pickup_date' => $data['pickup_date']->toDateString(),
                'return_date' => $data['return_date']->toDateString(),
                'passengers' => $data['passengers'],
                'unit_count' => $units,
                'with_driver' => $withDriver && $vehicle->driver_available,
                'total_price' => $quote['grand_total'],
                'status' => Booking::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->adjustInventory($booking, +1);

            return $booking;
        });
    }

    /**
     * Gives the units held by a booking back to the inventory.
     */
    public function releaseInventory(Booking $booking): void
    {
        $this->adjustInventory($booking, -1);
    }

    /**
     * Takes the units of a booking that had given them back (cancelled or
     * expired) out of the inventory again. Returns false, and changes nothing,
     * when those days can no longer host it.
     */
    public function holdInventory(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $quote = $this->quote(
                $booking->vehicle,
                CarbonImmutable::parse($booking->pickup_date),
                CarbonImmutable::parse($booking->return_date),
                $booking->unit_count,
                lockForUpdate: true,
            );

            if (! $quote) {
                return false;
            }

            $this->adjustInventory($booking, +1);

            return true;
        });
    }

    /**
     * Expires pending requests nobody has answered within the hold period and
     * gives their units back. Returns how many were expired.
     */
    public function expirePending(): int
    {
        $expired = 0;

        Booking::where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<=', now()->subHours(self::PENDING_HOLD_HOURS))
            ->each(function (Booking $booking) use (&$expired) {
                DB::transaction(function () use ($booking, &$expired) {
                    $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();

                    if ($locked?->status !== Booking::STATUS_PENDING) {
                        return;
                    }

                    $this->releaseInventory($locked);
                    $locked->update(['status' => Booking::STATUS_EXPIRED]);
                    $expired++;
                });
            });

        return $expired;
    }

    private function adjustInventory(Booking $booking, int $direction): void
    {
        VehicleInventory::where('vehicle_id', $booking->vehicle_id)
            ->whereDate('rental_date', '>=', $booking->pickup_date->toDateString())
            ->whereDate('rental_date', '<=', $booking->return_date->copy()->subDay()->toDateString())
            ->{$direction > 0 ? 'increment' : 'decrement'}('booked_units', $booking->unit_count);
    }
}
