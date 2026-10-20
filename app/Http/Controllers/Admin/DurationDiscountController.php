<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\DurationDiscount;
use App\Services\Audit\AuditLogger;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DurationDiscountController extends Controller
{
    use ResolvesCurrentRental;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * One percentage per threshold: saving "5 days" again replaces the old one.
     */
    public function store(Request $request): RedirectResponse
    {
        $rental = $this->currentRental($request);

        $data = $request->validate([
            'min_days' => ['required', 'integer', 'min:2', 'max:'.ReservationService::MAX_DAYS],
            'percent' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);

        $discount = $rental->durationDiscounts()->updateOrCreate(['min_days' => $data['min_days']], ['percent' => $data['percent']]);
        $this->audit->record($rental, $request->user(), 'pricing.duration_discount_saved', $discount, ['percent' => [null, $discount->percent]], $discount->min_days.' days');

        return back()->with('status', __('admin.pricing_saved'));
    }

    public function destroy(Request $request, DurationDiscount $durationDiscount): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($durationDiscount->rental_id !== $rental->id, 404);

        $durationDiscount->delete();
        $this->audit->record($rental, $request->user(), 'pricing.duration_discount_deleted', $durationDiscount, [], $durationDiscount->min_days.' days');

        return back()->with('status', __('admin.pricing_deleted'));
    }
}
