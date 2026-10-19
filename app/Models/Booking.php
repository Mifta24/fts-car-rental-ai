<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference',
    'rental_id',
    'vehicle_id',
    'conversation_id',
    'customer_name',
    'customer_email',
    'customer_phone',
    'contact_type',
    'pickup_date',
    'return_date',
    'passengers',
    'unit_count',
    'with_driver',
    'total_price',
    'status',
    'notes',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'return_date' => 'date',
            'with_driver' => 'boolean',
            'total_price' => 'decimal:2',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * A short, unambiguous, non-sequential code passengers can quote to staff.
     */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'BK-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Pending and confirmed bookings keep their units out of the inventory;
     * cancelled and expired ones have given them back.
     */
    public function holdsInventory(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);
    }

    /**
     * Whether the email or phone number given matches the one left at booking
     * time. Phone numbers compare by digits only, so spacing and "+" do not matter.
     */
    public function contactMatches(string $contact): bool
    {
        $contact = trim($contact);

        if ($contact === '') {
            return false;
        }

        if (filled($this->customer_email) && mb_strtolower($contact) === mb_strtolower($this->customer_email)) {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $contact);

        return strlen($digits) >= 6
            && filled($this->customer_phone)
            && $digits === preg_replace('/\D+/', '', $this->customer_phone);
    }

    /**
     * Whether the customer may still cancel online: the request is still alive
     * and the trip has not started.
     */
    public function cancellableByCustomer(): bool
    {
        return $this->holdsInventory()
            && $this->pickup_date->toDateString() >= now($this->rental->timezone)->toDateString();
    }

    public function days(): int
    {
        return $this->pickup_date->diffInDays($this->return_date);
    }
}
