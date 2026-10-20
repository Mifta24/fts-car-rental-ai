<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Services\Audit\AuditLogger;
use App\Services\Fleet\MaintenanceBlocker;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Service and repair records take the car out of the rentable inventory for
 * their dates; tax, insurance and inspection records only track validity.
 */
class VehicleMaintenanceController extends Controller
{
    use ResolvesCurrentRental;

    private const MAX_BLOCK_DAYS = 366;

    public function __construct(private readonly AuditLogger $audit, private readonly MaintenanceBlocker $blocker) {}

    public function index(Request $request, Vehicle $vehicle): View
    {
        $rental = $this->ownedBy($request, $vehicle);

        $records = $vehicle->maintenances()->orderByRaw('completed_at is null desc')->orderByDesc('starts_on')->orderByDesc('expires_on')->get();
        $editing = $request->query('edit') ? $records->firstWhere('id', (int) $request->query('edit')) : null;

        return view('admin.vehicles.maintenance', [
            'rental' => $rental,
            'vehicle' => $vehicle,
            'records' => $records,
            'editing' => $editing,
            'today' => now($rental->timezone)->startOfDay(),
            'alertWithinDays' => VehicleMaintenance::ALERT_WITHIN_DAYS,
        ]);
    }

    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $rental = $this->ownedBy($request, $vehicle);

        $data = $this->validated($request);

        if ($conflict = $this->conflictError($vehicle, $data)) {
            return back()->withErrors(['starts_on' => $conflict])->withInput();
        }

        $record = $vehicle->maintenances()->create($data);
        $this->blocker->sync($vehicle);
        $this->audit->record($rental, $request->user(), 'maintenance.created', $record, $this->summary($record), $vehicle->name);

        return redirect()->route('admin.vehicles.maintenance.index', $vehicle)->with('status', __('admin.maintenance_saved'));
    }

    public function update(Request $request, Vehicle $vehicle, VehicleMaintenance $maintenance): RedirectResponse
    {
        $rental = $this->ownedBy($request, $vehicle, $maintenance);

        $data = $this->validated($request);

        if ($conflict = $this->conflictError($vehicle, $data, $maintenance)) {
            return back()->withErrors(['starts_on' => $conflict])->withInput();
        }

        $maintenance->fill($data);
        $changes = $this->audit->dirtyChanges($maintenance);
        $maintenance->save();
        $this->blocker->sync($vehicle);
        $this->audit->record($rental, $request->user(), 'maintenance.updated', $maintenance, $changes, $vehicle->name);

        return redirect()->route('admin.vehicles.maintenance.index', $vehicle)->with('status', __('admin.maintenance_saved'));
    }

    public function complete(Request $request, Vehicle $vehicle, VehicleMaintenance $maintenance): RedirectResponse
    {
        $rental = $this->ownedBy($request, $vehicle, $maintenance);

        if ($maintenance->blocksInventory() && $maintenance->completed_at === null) {
            $maintenance->update(['completed_at' => now()]);
            $this->blocker->sync($vehicle);
            $this->audit->record($rental, $request->user(), 'maintenance.completed', $maintenance, $this->summary($maintenance), $vehicle->name);
        }

        return back()->with('status', __('admin.maintenance_completed'));
    }

    public function destroy(Request $request, Vehicle $vehicle, VehicleMaintenance $maintenance): RedirectResponse
    {
        $rental = $this->ownedBy($request, $vehicle, $maintenance);

        $maintenance->delete();
        $this->blocker->sync($vehicle);
        $this->audit->record($rental, $request->user(), 'maintenance.deleted', $maintenance, $this->summary($maintenance), $vehicle->name);

        return redirect()->route('admin.vehicles.maintenance.index', $vehicle)->with('status', __('admin.maintenance_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $blocking = in_array($request->input('type'), VehicleMaintenance::BLOCKING_TYPES, true);
        $expiry = in_array($request->input('type'), VehicleMaintenance::EXPIRY_TYPES, true);

        $data = $request->validate([
            'type' => ['required', Rule::in([...VehicleMaintenance::BLOCKING_TYPES, ...VehicleMaintenance::EXPIRY_TYPES])],
            'title' => ['nullable', 'string', 'max:255'],
            'starts_on' => [Rule::requiredIf($blocking), 'nullable', 'date_format:Y-m-d'],
            'ends_on' => [Rule::requiredIf($blocking), 'nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'expires_on' => [Rule::requiredIf($expiry), 'nullable', 'date_format:Y-m-d'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($blocking && CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on'])) >= self::MAX_BLOCK_DAYS) {
            throw ValidationException::withMessages(['ends_on' => __('admin.inventory_range_too_long', ['days' => self::MAX_BLOCK_DAYS])]);
        }

        return [
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'starts_on' => $blocking ? $data['starts_on'] : null,
            'ends_on' => $blocking ? $data['ends_on'] : null,
            'expires_on' => $expiry ? $data['expires_on'] : null,
            'cost' => $data['cost'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function conflictError(Vehicle $vehicle, array $data, ?VehicleMaintenance $replacing = null): ?string
    {
        if (! in_array($data['type'], VehicleMaintenance::BLOCKING_TYPES, true) || $replacing?->completed_at !== null) {
            return null;
        }

        $dates = $this->blocker->conflictingDates($vehicle, CarbonImmutable::parse($data['starts_on']), CarbonImmutable::parse($data['ends_on']), $replacing);

        return $dates->isEmpty()
            ? null
            : __('admin.maintenance_conflict', ['count' => $dates->count(), 'dates' => $dates->take(5)->implode(', ')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(VehicleMaintenance $record): array
    {
        return array_filter([
            'type' => $record->type,
            'from' => $record->starts_on?->toDateString(),
            'to' => $record->ends_on?->toDateString(),
            'expires_on' => $record->expires_on?->toDateString(),
        ]);
    }

    private function ownedBy(Request $request, Vehicle $vehicle, ?VehicleMaintenance $maintenance = null): Rental
    {
        $rental = $this->currentRental($request);

        abort_if($vehicle->rental_id !== $rental->id, 404);
        abort_if($maintenance !== null && $maintenance->vehicle_id !== $vehicle->id, 404);

        return $rental;
    }
}
