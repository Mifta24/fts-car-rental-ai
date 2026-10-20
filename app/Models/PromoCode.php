<?php

namespace App\Models;

use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rental_id', 'code', 'discount_type', 'discount_value', 'min_days', 'valid_from', 'valid_until', 'max_uses', 'is_active'])]
class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TYPES = [self::TYPE_PERCENT, self::TYPE_FIXED];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /**
     * Bookings that still hold a use of this code. Cancelled and expired
     * bookings give it back.
     */
    public function usesCount(): int
    {
        return Booking::where('rental_id', $this->rental_id)
            ->where('promo_code', $this->code)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->count();
    }

    /**
     * The discount this code gives on a rental price, never more than the price.
     */
    public function discountOn(float $rentalPrice): float
    {
        $discount = $this->discount_type === self::TYPE_PERCENT
            ? $rentalPrice * (float) $this->discount_value / 100
            : (float) $this->discount_value;

        return round(min($discount, $rentalPrice), 2);
    }
}
