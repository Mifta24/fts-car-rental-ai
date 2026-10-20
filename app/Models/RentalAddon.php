<?php

namespace App\Models;

use Database\Factories\RentalAddonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An optional extra a customer can add to a trip: child seat, GPS, ETC card,
 * insurance upgrade, airport delivery, one-way drop-off fee and the like.
 */
#[Fillable(['rental_id', 'name', 'translations', 'price', 'charge', 'is_active', 'sort_order'])]
class RentalAddon extends Model
{
    /** @use HasFactory<RentalAddonFactory> */
    use HasFactory;

    /** Price × days × cars. */
    public const CHARGE_PER_DAY = 'per_day';

    /** One flat amount for the whole booking. */
    public const CHARGE_PER_BOOKING = 'per_booking';

    public const CHARGES = [self::CHARGE_PER_DAY, self::CHARGE_PER_BOOKING];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function translatedName(string $locale): string
    {
        $value = $this->translations[$locale]['name'] ?? null;

        return filled($value) ? $value : $this->name;
    }

    public function totalFor(int $days, int $units): float
    {
        return (float) $this->price * ($this->charge === self::CHARGE_PER_DAY ? $days * $units : 1);
    }
}
