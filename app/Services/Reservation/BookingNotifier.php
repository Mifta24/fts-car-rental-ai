<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\HandoverRequest;
use App\Models\Rental;
use App\Models\User;
use App\Notifications\BookingCancelledByCustomer;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use App\Notifications\NewHandoverRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends the emails around a reservation: staff hear about new requests,
 * handovers and customer cancellations; the customer hears where their request
 * stands. A mail problem is reported but never fails the reservation itself.
 */
class BookingNotifier
{
    /**
     * A fresh request: staff are asked to review it, the customer gets a receipt.
     */
    public function requested(Booking $booking): void
    {
        $this->safely(function () use ($booking) {
            Notification::send($this->staff($booking->rental), new NewBookingRequest($booking));
        });

        $this->statusChanged($booking);
    }

    /**
     * Lets the customer know the new status of their reservation, when they left an email.
     */
    public function statusChanged(Booking $booking): void
    {
        if (blank($booking->customer_email)) {
            return;
        }

        $this->safely(function () use ($booking) {
            Notification::route('mail', $booking->customer_email)->notify(new BookingStatusChanged($booking));
        });
    }

    public function cancelledByCustomer(Booking $booking): void
    {
        $this->safely(function () use ($booking) {
            Notification::send($this->staff($booking->rental), new BookingCancelledByCustomer($booking));
        });

        $this->statusChanged($booking);
    }

    public function handoverRequested(HandoverRequest $handover): void
    {
        $this->safely(function () use ($handover) {
            Notification::send($this->staff($handover->conversation->rental), new NewHandoverRequest($handover));
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(Rental $rental): Collection
    {
        return $rental->users()->wherePivot('status', 'active')->get();
    }

    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
