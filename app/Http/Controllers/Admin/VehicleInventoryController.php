<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use App\Services\Audit\AuditLogger;
use App\Services\Fleet\MaintenanceBlocker;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The availability and daily price calendar of one vehicle. A day with no row
 * here cannot be quoted or booked, so staff must open the days they rent out.
 */
class VehicleInventoryController extends Controller
{
    use ResolvesCurrentRental;

    private const MAX_RANGE_DAYS = 366;

    private const LISTED_DAYS = 60;

    public function __construct(private readonly AuditLogger $audit, private readonly MaintenanceBlocker $blocker) {}

    public function index(Request $request, Vehicle $vehicle): View
    {
        $rental = $this->ownedBy($request, $vehicle);

        $from = $this->listStart($request, $rental->timezone);

        $days = $vehicle->inventory()
            ->whereDate('rental_date', '>=', $from->toDateString())
            ->whereDate('rental_date', '<=', $from->addDays(self::LISTED_DAYS - 1)->toDateString())
            ->orderBy('rental_date')
            ->get()
            ->keyBy(fn (VehicleInventory $day) => $day->rental_date->toDateString());

        $lastOpenDay = $vehicle->inventory()->whereDate('rental_date', '>=', now($rental->timezone)->toDateString())->max('rental_date');

        return view('admin.vehicles.inventory', [
            'rental' => $rental,
            'vehicle' => $vehicle,
            'from' => $from,
            'calendar' => collect(CarbonPeriod::create($from, $from->addDays(self::LISTED_DAYS - 1)))
                ->map(fn ($date) => ['date' => CarbonImmutable::instance($date), 'day' => $days->get($date->toDateString())]),
            'lastOpenDay' => $lastOpenDay ? CarbonImmutable::parse($lastOpenDay) : null,
            'maxRangeDays' => self::MAX_RANGE_DAYS,
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $rental = $this->ownedBy($request, $vehicle);
        $today = now($rental->timezone)->toDateString();

        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'total_units' => ['required', 'integer', 'min:0', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
        ]);

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);

        if ($start->diffInDays($end) >= self::MAX_RANGE_DAYS) {
            return back()->withErrors(['end_date' => __('admin.inventory_range_too_long', ['days' => self::MAX_RANGE_DAYS])])->withInput();
        }

        $weekdays = array_map('intval', $data['weekdays'] ?? []);
        $touched = 0;

        DB::transaction(function () use ($vehicle, $start, $end, $data, $weekdays, &$touched) {
            foreach (CarbonPeriod::create($start, $end) as $date) {
                if ($weekdays !== [] && ! in_array($date->dayOfWeek, $weekdays, true)) {
                    continue;
                }

                $day = VehicleInventory::where('vehicle_id', $vehicle->id)->whereDate('rental_date', $date->toDateString())->lockForUpdate()->first();

                // Units already promised to customers can never be taken away.
                $units = max((int) $data['total_units'], $day?->booked_units ?? 0);

                if ($day) {
                    $day->update(['total_units' => $units, 'price' => $data['price']]);
                } else {
                    VehicleInventory::create([
                        'vehicle_id' => $vehicle->id,
                        'rental_date' => CarbonImmutable::parse($date->toDateString()),
                        'total_units' => $units,
                        'booked_units' => 0,
                        'price' => $data['price'],
                    ]);
                }

                $touched++;
            }
        });

        $this->blocker->sync($vehicle);
        $this->audit->record($rental, $request->user(), 'inventory.updated', $vehicle, [
            'range' => [$start->toDateString(), $end->toDateString()],
            'days' => $touched,
            'total_units' => $data['total_units'],
            'price' => $data['price'],
        ], $vehicle->name);

        return redirect()
            ->route('admin.vehicles.inventory', ['vehicle' => $vehicle, 'from' => $start->toDateString()])
            ->with('status', __('admin.inventory_saved', ['count' => $touched]));
    }

    private function ownedBy(Request $request, Vehicle $vehicle): Rental
    {
        $rental = $this->currentRental($request);

        abort_if($vehicle->rental_id !== $rental->id, 404);

        return $rental;
    }

    private function listStart(Request $request, string $timezone): CarbonImmutable
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $from = $request->query('from');

        if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $from, $timezone)->startOfDay();
            } catch (\Throwable) {
                return $today;
            }
        }

        return $today;
    }
}
