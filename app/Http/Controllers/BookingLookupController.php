<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Rental;
use App\Services\Reservation\BookingNotifier;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Lets a customer look up their reservation and cancel it. The booking
 * reference alone is never enough: the email or phone number left at booking
 * time has to match too, and a miss never says which of the two was wrong.
 */
class BookingLookupController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly BookingNotifier $notifier,
    ) {}

    public function lookup(Request $request, string $rentalSlug): RedirectResponse
    {
        $rental = $this->publishedRental($rentalSlug);
        $data = $this->validated($request);

        if (! $this->findBooking($rental, $data)) {
            return $this->back($rental, $data)->with('booking_lookup_failed', true);
        }

        return $this->back($rental, $data)->with('booking_lookup', $data);
    }

    public function cancel(Request $request, string $rentalSlug): RedirectResponse
    {
        $rental = $this->publishedRental($rentalSlug);
        $data = $this->validated($request);

        $booking = $this->findBooking($rental, $data);

        if (! $booking) {
            return $this->back($rental, $data)->with('booking_lookup_failed', true);
        }

        $cancelled = DB::transaction(function () use ($booking) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();

            if (! $locked->cancellableByCustomer()) {
                return false;
            }

            $this->reservations->releaseInventory($locked);
            $locked->update(['status' => Booking::STATUS_CANCELLED]);

            return $locked;
        });

        if (! $cancelled) {
            return $this->back($rental, $data)->with('booking_lookup', $data)->with('booking_cancel_refused', true);
        }

        $this->notifier->cancelledByCustomer($cancelled);

        return $this->back($rental, $data)->with('booking_lookup', $data)->with('booking_cancelled', true);
    }

    /**
     * @return array{reference: string, contact: string, lang: string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'contact' => ['required', 'string', 'max:120'],
            'lang' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ]);

        return ['reference' => $data['reference'], 'contact' => $data['contact'], 'lang' => $data['lang'] ?? ''];
    }

    /**
     * @param  array{reference: string, contact: string}  $data
     */
    private function findBooking(Rental $rental, array $data): ?Booking
    {
        $booking = $rental->bookings()
            ->where('reference', strtoupper(trim($data['reference'])))
            ->first();

        return $booking?->contactMatches($data['contact']) ? $booking : null;
    }

    /**
     * @param  array{lang: string}  $data
     */
    private function back(Rental $rental, array $data): RedirectResponse
    {
        return redirect()->route('rental.booking', array_filter(['rentalSlug' => $rental->slug, 'lang' => $data['lang']]));
    }

    private function publishedRental(string $rentalSlug): Rental
    {
        $rental = Rental::where('slug', $rentalSlug)->first();

        abort_if(! $rental || ! $rental->isPublished(), 404);

        return $rental;
    }
}
