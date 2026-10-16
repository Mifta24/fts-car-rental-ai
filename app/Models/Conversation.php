<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'rental_id',
    'customer_token',
    'customer_name',
    'customer_email',
    'locale',
    'status',
    'reply_status',
    'reply_started_at',
    'handover_summary',
    'current_scene',
    'selected_vehicle_id',
    'selected_service_id',
    'reservation_state',
    'last_message_at',
])]
class Conversation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_HANDED_OVER = 'handed_over';

    public const STATUS_CLOSED = 'closed';

    public const REPLY_PROCESSING = 'processing';

    public const REPLY_FAILED = 'failed';

    /**
     * A reply still "processing" after this long was lost with its worker.
     */
    private const REPLY_STALE_AFTER_SECONDS = 120;

    /**
     * The UI scenes the customer can be in while talking to the agent.
     */
    public const SCENES = ['showroom', 'reception', 'vehicles', 'vehicle_detail', 'services', 'service_detail', 'reservation', 'handover'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'reply_started_at' => 'datetime',
            'reservation_state' => 'array',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function selectedVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'selected_vehicle_id');
    }

    public function selectedService(): BelongsTo
    {
        return $this->belongsTo(RentalKnowledgeItem::class, 'selected_service_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function handoverRequest(): HasOne
    {
        return $this->hasOne(HandoverRequest::class)->latestOfMany();
    }

    /**
     * Whether the agent is working on a reply: 'processing', 'failed' (the last
     * turn got no answer and can be retried) or null when idle. A reply whose
     * worker vanished counts as failed instead of keeping the customer waiting.
     */
    public function currentReplyStatus(): ?string
    {
        if ($this->reply_status === self::REPLY_PROCESSING && $this->reply_started_at?->lt(now()->subSeconds(self::REPLY_STALE_AFTER_SECONDS))) {
            return self::REPLY_FAILED;
        }

        return $this->reply_status;
    }

    public function isHandedOver(): bool
    {
        return $this->status === self::STATUS_HANDED_OVER;
    }
}
