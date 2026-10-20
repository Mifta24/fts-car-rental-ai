<?php

namespace App\Models;

use Database\Factories\VehicleMaintenanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable([
    'vehicle_id',
    'type',
    'title',
    'starts_on',
    'ends_on',
    'expires_on',
    'cost',
    'notes',
    'completed_at',
])]
class VehicleMaintenance extends Model
{
    /** @use HasFactory<VehicleMaintenanceFactory> */
    use HasFactory;

    public const TYPE_SERVICE = 'service';

    public const TYPE_REPAIR = 'repair';

    public const TYPE_TAX = 'tax';

    public const TYPE_INSURANCE = 'insurance';

    public const TYPE_INSPECTION = 'inspection';

    /** Take the car out of the rentable inventory between two dates. */
    public const BLOCKING_TYPES = [self::TYPE_SERVICE, self::TYPE_REPAIR];

    /** Documents with a validity date; they warn but never block bookings. */
    public const EXPIRY_TYPES = [self::TYPE_TAX, self::TYPE_INSURANCE, self::TYPE_INSPECTION];

    public const ALERT_WITHIN_DAYS = 30;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'expires_on' => 'date',
            'cost' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function blocksInventory(): bool
    {
        return in_array($this->type, self::BLOCKING_TYPES, true);
    }

    public function isActiveBlock(): bool
    {
        return $this->blocksInventory() && $this->completed_at === null;
    }

    /**
     * The newest validity date of each document type per vehicle that has run
     * out or runs out within the warning window. Renewing means adding a record
     * with a later date, which silences the old one.
     *
     * @return Collection<int, self>
     */
    public static function expiryAlerts(Rental $rental, int $withinDays = self::ALERT_WITHIN_DAYS): Collection
    {
        $limit = now($rental->timezone)->addDays($withinDays)->toDateString();

        return static::query()
            ->whereIn('type', self::EXPIRY_TYPES)
            ->whereHas('vehicle', fn ($q) => $q->where('rental_id', $rental->id))
            ->with('vehicle')
            ->get()
            ->groupBy(fn (self $m) => $m->vehicle_id.'-'.$m->type)
            ->map(fn (Collection $group) => $group->sortByDesc('expires_on')->first())
            ->filter(fn (self $m) => $m->expires_on->toDateString() <= $limit)
            ->sortBy('expires_on')
            ->values();
    }
}
