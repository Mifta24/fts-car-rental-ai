<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only source of truth for availability and price. Stands in for a real
 * PMS/booking-engine adapter for V1 — the AI must reach this table (or its
 * future adapter) through a tool call, never answer from its own memory.
 */
#[Fillable([
    'vehicle_id',
    'rental_date',
    'total_units',
    'booked_units',
    'price',
])]
class VehicleInventory extends Model
{
    protected $table = 'vehicle_inventory';

    protected function casts(): array
    {
        return [
            'rental_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function availableUnits(): int
    {
        return max(0, $this->total_units - $this->booked_units);
    }

    public function isAvailable(): bool
    {
        return $this->availableUnits() > 0;
    }
}
