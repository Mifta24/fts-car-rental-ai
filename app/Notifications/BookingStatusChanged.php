<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\Reservation\ReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the customer, by email, where their reservation request stands:
 * received, confirmed, cancelled or expired.
 */
class BookingStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
        $this->locale($booking->conversation?->locale ?? $booking->rental->default_locale);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $rental = $booking->rental;

        return (new MailMessage)
            ->subject(__('notifications.booking_status.subject.'.$booking->status, ['reference' => $booking->reference, 'rental' => $rental->name]))
            ->greeting(__('notifications.booking_status.greeting', ['name' => $booking->customer_name]))
            ->line(__('notifications.booking_status.body.'.$booking->status, ['rental' => $rental->name, 'hours' => ReservationService::PENDING_HOLD_HOURS]))
            ->line(__('notifications.booking_details', [
                'reference' => $booking->reference,
                'customer' => $booking->customer_name,
                'vehicle' => $booking->vehicle->translatedName($this->locale ?? $rental->default_locale),
                'pickup' => $booking->pickup_date->toDateString(),
                'return' => $booking->return_date->toDateString(),
                'total' => $rental->currency.' '.number_format((float) $booking->total_price, 0, ',', '.'),
            ]))
            ->line(__('notifications.booking_status.contact', ['phone' => $rental->phone ?: ($rental->email ?: '-')]));
    }
}
