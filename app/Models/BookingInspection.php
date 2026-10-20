<?php

namespace App\Models;

use Database\Factories\BookingInspectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The signed condition report taken when a car leaves (pickup) and when it
 * comes back (return): odometer, fuel, photos, notes and any extra charges.
 */
#[Fillable([
    'booking_id',
    'type',
    'inspected_by',
    'odometer_km',
    'fuel_percent',
    'condition_notes',
    'photos',
    'signed_by',
    'signature_path',
    'late_fee',
    'damage_fee',
    'fee_notes',
    'completed_at',
])]
class BookingInspection extends Model
{
    /** @use HasFactory<BookingInspectionFactory> */
    use HasFactory;

    public const TYPE_PICKUP = 'pickup';

    public const TYPE_RETURN = 'return';

    public const TYPES = [self::TYPE_PICKUP, self::TYPE_RETURN];

    public const FUEL_LEVELS = [0, 25, 50, 75, 100];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'late_fee' => 'decimal:2',
            'damage_fee' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function extraCharges(): float
    {
        return (float) $this->late_fee + (float) $this->damage_fee;
    }
}
