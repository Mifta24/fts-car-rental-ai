<?php

namespace App\Services\Reservation;

use App\Exceptions\PricingOptionRejected;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\DurationDiscount;
use App\Models\PromoCode;
use App\Models\Rental;
use App\Models\RentalAddon;
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

    public function __construct(private readonly BookingNotifier $notifier = new BookingNotifier) {}

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
     * `vehicle_total` is the gross rental price; discounts are listed apart and
     * already subtracted from `grand_total`. Throws PricingOptionRejected when a
     * requested add-on or promo code cannot be applied.
     *
     * @param  list<int>  $addonIds
     * @return array{days: int, daily: list<array{date: string, price: float}>, vehicle_total: float, driver_total: float, addons: list<array{id: int, name: string, charge: string, price: float, total: float}>, addons_total: float, duration_discount: array{percent: float, amount: float}, promo: array{code: string, amount: float}|null, discount_total: float, grand_total: float, min_available_units: int}|null
     */
    public function quote(
        Vehicle $vehicle,
        CarbonImmutable $pickupDate,
        CarbonImmutable $returnDate,
        int $units = 1,
        bool $withDriver = false,
        bool $lockForUpdate = false,
        array $addonIds = [],
        ?string $promoCode = null,
        string $locale = '',
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

        $addons = $this->resolveAddons($vehicle, $addonIds, $days, $units, $locale);
        $addonsTotal = array_sum(array_column($addons, 'total'));

        $durationPercent = (float) (DurationDiscount::where('rental_id', $vehicle->rental_id)->where('min_days', '<=', $days)->max('percent') ?? 0);
        $durationAmount = round($vehicleTotal * $durationPercent / 100, 2);

        $promo = $promoCode !== null && trim($promoCode) !== ''
            ? $this->resolvePromo($vehicle, $promoCode, $days)
            : null;
        $promoAmount = $promo ? $promo->discountOn($vehicleTotal - $durationAmount) : 0.0;

        return [
            'days' => $days,
            'daily' => $daily,
            'vehicle_total' => $vehicleTotal,
            'driver_total' => $driverTotal,
            'addons' => $addons,
            'addons_total' => $addonsTotal,
            'duration_discount' => ['percent' => $durationPercent, 'amount' => $durationAmount],
            'promo' => $promo ? ['code' => $promo->code, 'amount' => $promoAmount] : null,
            'discount_total' => $durationAmount + $promoAmount,
            'grand_total' => $vehicleTotal - $durationAmount - $promoAmount + $driverTotal + $addonsTotal,
            'min_available_units' => $minAvailable,
        ];
    }

    /**
     * @param  list<int>  $addonIds
     * @return list<array{id: int, name: string, charge: string, price: float, total: float}>
     */
    private function resolveAddons(Vehicle $vehicle, array $addonIds, int $days, int $units, string $locale): array
    {
        $ids = array_values(array_unique(array_map('intval', $addonIds)));

        if ($ids === []) {
            return [];
        }

        $addons = RentalAddon::where('rental_id', $vehicle->rental_id)->where('is_active', true)->whereIn('id', $ids)->orderBy('sort_order')->orderBy('id')->get();

        if ($addons->count() !== count($ids)) {
            throw new PricingOptionRejected('addons', 'addon_unknown');
        }

        return $addons->map(fn (RentalAddon $addon) => [
            'id' => $addon->id,
            'name' => $addon->translatedName($locale),
            'charge' => $addon->charge,
            'price' => (float) $addon->price,
            'total' => $addon->totalFor($days, $units),
        ])->all();
    }

    private function resolvePromo(Vehicle $vehicle, string $code, int $days): PromoCode
    {
        $promo = PromoCode::where('rental_id', $vehicle->rental_id)->where('code', PromoCode::normalize($code))->where('is_active', true)->first();

        if (! $promo) {
            throw new PricingOptionRejected('promo_code', 'promo_invalid');
        }

        $today = now($vehicle->rental->timezone)->toDateString();

        if (($promo->valid_from && $promo->valid_from->toDateString() > $today) || ($promo->valid_until && $promo->valid_until->toDateString() < $today)) {
            throw new PricingOptionRejected('promo_code', 'promo_expired');
        }

        if ($days < $promo->min_days) {
            throw new PricingOptionRejected('promo_code', 'promo_min_days');
        }

        if ($promo->max_uses !== null && $promo->usesCount() >= $promo->max_uses) {
            throw new PricingOptionRejected('promo_code', 'promo_used_up');
        }

        return $promo;
    }

    /**
     * Creates a pending reservation request and holds the inventory for it.
     * Returns null when the vehicle is no longer available for those days.
     *
     * @param  array{pickup_date: CarbonImmutable, return_date: CarbonImmutable, passengers: int, units?: int, with_driver?: bool, addon_ids?: list<int>, promo_code?: ?string, customer_name: string, customer_email?: ?string, customer_phone?: ?string, contact_type?: ?string, notes?: ?string}  $data
     */
    public function createRequest(Rental $rental, Vehicle $vehicle, array $data, ?Conversation $conversation = null): ?Booking
    {
        $units = $data['units'] ?? 1;
        $withDriver = (bool) ($data['with_driver'] ?? false);

        $booking = DB::transaction(function () use ($rental, $vehicle, $data, $conversation, $units, $withDriver) {
            $quote = $this->quote($vehicle, $data['pickup_date'], $data['return_date'], $units, $withDriver, lockForUpdate: true, addonIds: $data['addon_ids'] ?? [], promoCode: $data['promo_code'] ?? null);

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
                'addons' => $quote['addons'] === [] ? null : $quote['addons'],
                'duration_discount' => $quote['duration_discount']['amount'],
                'promo_code' => $quote['promo']['code'] ?? null,
                'promo_discount' => $quote['promo']['amount'] ?? 0,
                'total_price' => $quote['grand_total'],
                'status' => Booking::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->adjustInventory($booking, +1);

            return $booking;
        });

        if ($booking) {
            $this->notifier->requested($booking);
        }

        return $booking;
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
        $expiredBookings = [];

        Booking::where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<=', now()->subHours(self::PENDING_HOLD_HOURS))
            ->each(function (Booking $booking) use (&$expired, &$expiredBookings) {
                DB::transaction(function () use ($booking, &$expired, &$expiredBookings) {
                    $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();

                    if ($locked?->status !== Booking::STATUS_PENDING) {
                        return;
                    }

                    $this->releaseInventory($locked);
                    $locked->update(['status' => Booking::STATUS_EXPIRED]);
                    $expired++;
                    $expiredBookings[] = $locked;
                });
            });

        foreach ($expiredBookings as $booking) {
            $this->notifier->statusChanged($booking);
        }

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
