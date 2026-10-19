<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells rental staff that a customer cancelled their own reservation.
 */
class BookingCancelledByCustomer extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject(__('notifications.customer_cancelled.subject', ['reference' => $this->booking->reference]))
            ->line(__('notifications.customer_cancelled.intro', ['customer' => $this->booking->customer_name, 'reference' => $this->booking->reference]))
            ->action(__('notifications.new_booking.action'), route('admin.bookings.index', ['status' => 'cancelled']));
    }
}
