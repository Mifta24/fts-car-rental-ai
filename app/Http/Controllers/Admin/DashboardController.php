<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HandoverRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesCurrentRental;

    public function index(Request $request): View
    {
        $rental = $this->currentRental($request);

        $stats = [
            'vehicles' => $rental->vehicles()->count(),
            'knowledge_items' => $rental->knowledgeItems()->count(),
            'pending_bookings' => $rental->bookings()->where('status', Booking::STATUS_PENDING)->count(),
            'open_handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('rental_id', $rental->id))
                ->where('status', HandoverRequest::STATUS_OPEN)
                ->count(),
        ];

        $recentBookings = $rental->bookings()->latest()->take(5)->with('vehicle')->get();

        $openHandovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('rental_id', $rental->id))
            ->where('status', HandoverRequest::STATUS_OPEN)
            ->latest()
            ->take(5)
            ->with('conversation')
            ->get();

        return view('admin.dashboard', [
            'rental' => $rental,
            'stats' => $stats,
            'recentBookings' => $recentBookings,
            'openHandovers' => $openHandovers,
        ]);
    }
}
