<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingInspection;
use App\Models\Rental;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pickup and return condition reports. Files live on the private disk and are
 * only served to staff of the booking's rental.
 */
class BookingInspectionController extends Controller
{
    use ResolvesCurrentRental;

    private const MAX_PHOTOS = 10;

    private const MAX_SIGNATURE_BYTES = 300_000;

    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request, Booking $booking, string $type): View|RedirectResponse
    {
        $rental = $this->ownedBy($request, $booking);
        $booking->load('vehicle', 'inspections.inspector');
        $inspection = $booking->inspection($type);

        $blocked = $inspection ? null : $this->blockedReason($booking, $type);
        $pickup = $booking->inspection(BookingInspection::TYPE_PICKUP);

        return view('admin.inspections.show', [
            'rental' => $rental,
            'booking' => $booking,
            'type' => $type,
            'inspection' => $inspection,
            'pickup' => $pickup,
            'blocked' => $blocked,
            'fuelLevels' => BookingInspection::FUEL_LEVELS,
            'lateDays' => max(0, (int) $booking->return_date->copy()->startOfDay()->diffInDays(now($rental->timezone)->startOfDay(), false)),
        ]);
    }

    public function store(Request $request, Booking $booking, string $type): RedirectResponse
    {
        $rental = $this->ownedBy($request, $booking);
        $booking->load('inspections');

        if ($blocked = $this->blockedReason($booking, $type)) {
            return back()->with('error', $blocked);
        }

        $isReturn = $type === BookingInspection::TYPE_RETURN;
        $pickup = $booking->inspection(BookingInspection::TYPE_PICKUP);

        $data = $request->validate([
            'odometer_km' => ['required', 'integer', 'min:'.($isReturn ? $pickup->odometer_km : 0), 'max:2000000'],
            'fuel_percent' => ['required', Rule::in(BookingInspection::FUEL_LEVELS)],
            'condition_notes' => ['nullable', 'string', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'signed_by' => ['required', 'string', 'max:120'],
            'signature' => ['required', 'string'],
            'late_fee' => $isReturn ? ['nullable', 'numeric', 'min:0', 'max:999999999'] : ['prohibited'],
            'damage_fee' => $isReturn ? ['nullable', 'numeric', 'min:0', 'max:999999999'] : ['prohibited'],
            'fee_notes' => $isReturn ? ['nullable', 'string', 'max:1000'] : ['prohibited'],
        ]);

        $signature = $this->decodeSignature($data['signature']);
        $directory = "inspections/{$booking->id}";

        $photos = collect($request->file('photos', []))
            ->map(fn ($file) => $file->storeAs($directory, Str::uuid().'.'.$file->extension(), 'local'))
            ->all();
        $signaturePath = "{$directory}/signature-{$type}-".Str::uuid().'.png';
        Storage::disk('local')->put($signaturePath, $signature);

        $inspection = $booking->inspections()->create([
            'type' => $type,
            'inspected_by' => $request->user()->id,
            'odometer_km' => $data['odometer_km'],
            'fuel_percent' => $data['fuel_percent'],
            'condition_notes' => $data['condition_notes'] ?? null,
            'photos' => $photos,
            'signed_by' => $data['signed_by'],
            'signature_path' => $signaturePath,
            'late_fee' => $data['late_fee'] ?? 0,
            'damage_fee' => $data['damage_fee'] ?? 0,
            'fee_notes' => $data['fee_notes'] ?? null,
            'completed_at' => now(),
        ]);

        $this->audit->record($rental, $request->user(), "inspection.{$type}_completed", $inspection, array_filter([
            'odometer_km' => $inspection->odometer_km,
            'fuel_percent' => $inspection->fuel_percent,
            'photos' => count($photos),
            'extra_charges' => $inspection->extraCharges() ?: null,
        ]), $booking->reference);

        return redirect()->route('admin.bookings.inspection.show', [$booking, $type])->with('status', __('admin.inspection_saved'));
    }

    public function photo(Request $request, BookingInspection $inspection, int $index): StreamedResponse
    {
        $this->ownedBy($request, $inspection->booking);

        $path = $inspection->photos[$index] ?? null;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function signature(Request $request, BookingInspection $inspection): StreamedResponse
    {
        $this->ownedBy($request, $inspection->booking);

        abort_if(! $inspection->signature_path || ! Storage::disk('local')->exists($inspection->signature_path), 404);

        return Storage::disk('local')->response($inspection->signature_path);
    }

    private function blockedReason(Booking $booking, string $type): ?string
    {
        if ($booking->inspection($type)) {
            return __('admin.inspection_exists');
        }

        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            return __('admin.inspection_needs_confirmed');
        }

        if ($type === BookingInspection::TYPE_RETURN && ! $booking->inspection(BookingInspection::TYPE_PICKUP)) {
            return __('admin.inspection_needs_pickup');
        }

        return null;
    }

    private function decodeSignature(string $dataUrl): string
    {
        $prefix = 'data:image/png;base64,';
        $binary = str_starts_with($dataUrl, $prefix) ? base64_decode(substr($dataUrl, strlen($prefix)), true) : false;

        if ($binary === false || strlen($binary) > self::MAX_SIGNATURE_BYTES || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            throw ValidationException::withMessages(['signature' => __('admin.inspection_signature_invalid')]);
        }

        return $binary;
    }

    private function ownedBy(Request $request, Booking $booking): Rental
    {
        $rental = $this->currentRental($request);

        abort_if($booking->rental_id !== $rental->id, 404);

        return $rental;
    }
}
