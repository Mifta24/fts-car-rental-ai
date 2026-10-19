<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\Reservation\ReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells rental staff that a customer sent a new reservation request.
 */
class NewBookingRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
        $this->locale($booking->rental->default_locale);
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

        return (new MailMessage)
            ->subject(__('notifications.new_booking.subject', ['reference' => $booking->reference]))
            ->line(__('notifications.new_booking.intro', ['expires' => ReservationService::PENDING_HOLD_HOURS]))
            ->line(__('notifications.booking_details', [
                'reference' => $booking->reference,
                'customer' => $booking->customer_name,
                'vehicle' => $booking->vehicle->name,
                'pickup' => $booking->pickup_date->toDateString(),
                'return' => $booking->return_date->toDateString(),
                'total' => $booking->rental->currency.' '.number_format((float) $booking->total_price, 0, ',', '.'),
            ]))
            ->action(__('notifications.new_booking.action'), route('admin.bookings.index', ['status' => 'pending']));
    }
}
