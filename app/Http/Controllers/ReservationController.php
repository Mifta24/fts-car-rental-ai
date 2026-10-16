<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\Reservation\ReservationHandover;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    private const PHONE_PATTERN = '/^\+?[0-9\s\-().]{6,20}$/';

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ReservationHandover $handover,
    ) {}

    public function quote(Request $request, string $rentalSlug): JsonResponse
    {
        $rental = $this->publishedRental($rentalSlug);
        $locale = $this->locale($request, $rental);

        $data = $request->validate($this->tripRules($rental), $this->validationMessages($locale));
        [$vehicle, $pickupDate, $returnDate, $units, $withDriver] = $this->resolveTrip($rental, $data, $locale);

        $quote = $this->reservations->quote($vehicle, $pickupDate, $returnDate, $units, $withDriver);

        if (! $quote) {
            return $this->unavailable($rental, $vehicle, $data, $locale);
        }

        return response()->json([
            'available' => true,
            'days' => $quote['days'],
            'vehicle_total' => $quote['vehicle_total'],
            'driver_total' => $quote['driver_total'],
            'grand_total' => $quote['grand_total'],
            'currency' => $rental->currency,
        ]);
    }

    public function store(Request $request, string $rentalSlug): JsonResponse
    {
        $rental = $this->publishedRental($rentalSlug);
        $locale = $this->locale($request, $rental);
        $messages = trans('reservation.messages', [], $locale);

        $data = $request->validate([
            ...$this->tripRules($rental),
            'customer_name' => ['required', 'string', 'max:100'],
            'contact_type' => ['required', Rule::in(['whatsapp', 'phone', 'email'])],
            'contact_value' => ['required', 'string', 'max:120'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'customer_token' => ['nullable', 'uuid'],
        ], $this->validationMessages($locale));

        $isEmail = $data['contact_type'] === 'email';

        if ($isEmail && ! filter_var($data['contact_value'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_email']]);
        }

        if (! $isEmail && ! preg_match(self::PHONE_PATTERN, $data['contact_value'])) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_phone']]);
        }

        [$vehicle, $pickupDate, $returnDate, $units, $withDriver] = $this->resolveTrip($rental, $data, $locale);

        $conversation = isset($data['customer_token'])
            ? Conversation::where('rental_id', $rental->id)->where('customer_token', $data['customer_token'])->first()
            : null;

        $booking = $this->reservations->createRequest($rental, $vehicle, [
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'passengers' => (int) $data['passengers'],
            'units' => $units,
            'with_driver' => $withDriver,
            'customer_name' => $data['customer_name'],
            'customer_email' => $isEmail ? $data['contact_value'] : null,
            'customer_phone' => $isEmail ? null : $data['contact_value'],
            'contact_type' => $data['contact_type'],
            'notes' => $data['special_request'] ?? null,
        ], $conversation);

        if (! $booking) {
            return $this->unavailable($rental, $vehicle, $data, $locale);
        }

        return response()->json([
            'reference' => $booking->reference,
            'status' => $booking->status,
            'total' => (float) $booking->total_price,
            'currency' => $rental->currency,
            'handover' => $this->handover->forBooking($rental, $booking, $locale),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function tripRules(Rental $rental): array
    {
        $today = now($rental->timezone)->toDateString();

        return [
            'vehicle_slug' => ['required', 'string', 'max:120'],
            'pickup_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'return_date' => ['required', 'date_format:Y-m-d', 'after:pickup_date'],
            'passengers' => ['required', 'integer', 'min:1', 'max:20'],
            'units' => ['required', 'integer', 'min:1', 'max:10'],
            'with_driver' => ['nullable', 'boolean'],
            'locale' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(string $locale): array
    {
        $messages = trans('reservation.messages', [], $locale);

        return [
            'required' => $messages['invalid'],
            'date_format' => $messages['invalid'],
            'integer' => $messages['invalid'],
            'min' => $messages['invalid'],
            'max' => $messages['invalid'],
            'boolean' => $messages['invalid'],
            'in' => $messages['invalid'],
            'uuid' => $messages['invalid'],
            'after_or_equal' => $messages['pickup_date_past'],
            'after' => $messages['return_date_after'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Vehicle, 1: CarbonImmutable, 2: CarbonImmutable, 3: int, 4: bool}
     */
    private function resolveTrip(Rental $rental, array $data, string $locale): array
    {
        $messages = trans('reservation.messages', [], $locale);

        $vehicle = $rental->vehicles()->where('is_active', true)->where('slug', $data['vehicle_slug'])->first();

        if (! $vehicle) {
            throw ValidationException::withMessages(['vehicle_slug' => $messages['vehicle_unknown']]);
        }

        $pickupDate = CarbonImmutable::parse($data['pickup_date'])->startOfDay();
        $returnDate = CarbonImmutable::parse($data['return_date'])->startOfDay();

        if ($pickupDate->diffInDays($returnDate) > ReservationService::MAX_DAYS) {
            throw ValidationException::withMessages([
                'return_date' => str_replace(':max', (string) ReservationService::MAX_DAYS, $messages['too_long']),
            ]);
        }

        $units = (int) $data['units'];

        if (! $this->reservations->fitsPassengers($vehicle, (int) $data['passengers'], $units)) {
            throw ValidationException::withMessages(['passengers' => $messages['capacity']]);
        }

        return [$vehicle, $pickupDate, $returnDate, $units, (bool) ($data['with_driver'] ?? false)];
    }

    /**
     * The vehicle cannot be booked for those days: say so, and offer the vehicle
     * types that can host the same party on the same dates.
     *
     * @param  array<string, mixed>  $data
     */
    private function unavailable(Rental $rental, Vehicle $requested, array $data, string $locale): JsonResponse
    {
        $pickupDate = CarbonImmutable::parse($data['pickup_date'])->startOfDay();
        $returnDate = CarbonImmutable::parse($data['return_date'])->startOfDay();
        $units = (int) $data['units'];
        $passengers = (int) $data['passengers'];

        $alternatives = $rental->vehicles()
            ->where('is_active', true)
            ->whereKeyNot($requested->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Vehicle $vehicle) => $this->reservations->fitsPassengers($vehicle, $passengers, $units))
            ->map(function (Vehicle $vehicle) use ($pickupDate, $returnDate, $units, $locale) {
                $quote = $this->reservations->quote($vehicle, $pickupDate, $returnDate, $units);

                return $quote ? [
                    'slug' => $vehicle->slug,
                    'name' => $vehicle->translatedName($locale),
                    'total' => $quote['grand_total'],
                ] : null;
            })
            ->filter()
            ->values();

        $message = trans('reservation.messages', [], $locale)['unavailable'];

        return response()->json([
            'message' => $message,
            'errors' => ['vehicle_slug' => [$message]],
            'alternatives' => $alternatives,
            'currency' => $rental->currency,
        ], 422);
    }

    private function locale(Request $request, Rental $rental): string
    {
        return in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $rental->default_locale;
    }

    private function publishedRental(string $rentalSlug): Rental
    {
        $rental = Rental::where('slug', $rentalSlug)->first();

        abort_if(! $rental || ! $rental->isPublished(), 404);

        return $rental;
    }
}
