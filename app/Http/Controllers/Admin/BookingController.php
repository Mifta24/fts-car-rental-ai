<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Audit\AuditLogger;
use App\Services\Reservation\BookingNotifier;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    use ResolvesCurrentRental;

    public function __construct(private readonly ReservationService $reservations, private readonly BookingNotifier $notifier, private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $rental = $this->currentRental($request);

        $status = in_array($request->query('status'), [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED], true)
            ? $request->query('status')
            : null;

        $bookings = $rental->bookings()
            ->with('vehicle', 'inspections')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.bookings.index', compact('rental', 'bookings', 'status'));
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($booking->rental_id !== $rental->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED])],
        ]);

        if ($booking->status === $data['status']) {
            return back();
        }

        if ($data['status'] === Booking::STATUS_CANCELLED) {
            if ($booking->holdsInventory()) {
                $this->reservations->releaseInventory($booking);
            }
        } elseif (! $booking->holdsInventory() && ! $this->reservations->holdInventory($booking)) {
            return back()->with('error', __('admin.booking_unavailable', ['reference' => $booking->reference]));
        }

        $previousStatus = $booking->status;
        $booking->update(['status' => $data['status']]);
        $this->audit->record($rental, $request->user(), 'booking.status_changed', $booking, ['status' => [$previousStatus, $data['status']]], $booking->reference);
        $this->notifier->statusChanged($booking);

        return back()->with('status', __('admin.booking_updated', ['reference' => $booking->reference, 'status' => __('admin.booking_statuses.'.$data['status'])]));
    }
}
