<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\RentalKnowledgeItem;
use App\Models\Vehicle;
use App\Services\Reservation\ReservationHandover;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RentalPageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function __construct(private readonly ReservationHandover $handover) {}

    public function index(Request $request): View
    {
        $rentals = Rental::where('public_status', 'published')->orderBy('name')->get();

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : ($rentals->first()?->default_locale ?? 'id');

        return view('opening', [
            'rentals' => $rentals,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'opening' => $this->lines('opening', $locale),
        ]);
    }

    public function show(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.show', $this->stageData($rental, $locale, 'showroom'));
    }

    public function vehicles(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.vehicles-index', $this->stageData($rental, $locale, 'vehicles'));
    }

    public function vehicle(Request $request, string $rentalSlug, string $vehicleSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        $data = $this->stageData($rental, $locale, 'vehicle');
        $vehicles = $data['vehicles'];
        $index = $vehicles->search(fn (Vehicle $vehicle) => $vehicle->slug === $vehicleSlug);

        abort_if($index === false, 404);

        return view('rental.vehicle-detail', [
            ...$data,
            'vehicle' => $vehicles[$index],
            'vehicleIndex' => $index,
            'previousVehicle' => $vehicles[($index - 1 + $vehicles->count()) % $vehicles->count()],
            'nextVehicle' => $vehicles[($index + 1) % $vehicles->count()],
        ]);
    }

    public function services(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.services-index', $this->stageData($rental, $locale, 'services'));
    }

    public function service(Request $request, string $rentalSlug, int $serviceId): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        $data = $this->stageData($rental, $locale, 'service');
        $services = $data['services'];
        $index = $services->search(fn (RentalKnowledgeItem $item) => $item->id === $serviceId);

        abort_if($index === false, 404);

        return view('rental.service-detail', [
            ...$data,
            'service' => $services[$index],
            'serviceIndex' => $index,
            'previousService' => $services[($index - 1 + $services->count()) % $services->count()],
            'nextService' => $services[($index + 1) % $services->count()],
        ]);
    }

    public function info(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.info-index', $this->stageData($rental, $locale, 'info'));
    }

    public function staff(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.staff-index', $this->stageData($rental, $locale, 'staff'));
    }

    public function booking(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        $lookup = session('booking_lookup');
        $booking = $lookup
            ? $rental->bookings()->where('reference', strtoupper(trim($lookup['reference'])))->with('vehicle')->first()
            : null;

        return view('rental.booking-lookup', [
            ...$this->stageData($rental, $locale, 'staff'),
            'lookup' => $lookup,
            'booking' => $booking?->contactMatches($lookup['contact']) ? $booking : null,
            'lines' => trans('booking', [], $locale),
            'canCancel' => $booking?->contactMatches($lookup['contact']) && $booking->cancellableByCustomer(),
        ]);
    }

    public function reservationScene(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.reservation-index', [
            ...$this->stageData($rental, $locale, 'reservation'),
            'preselectedVehicle' => (string) $request->query('vehicle', ''),
            'prefill' => $this->reservationPrefill($request),
            'addons' => $rental->addons()->where('is_active', true)->get(),
            'hasPromoCodes' => $rental->promoCodes()->where('is_active', true)->exists(),
        ]);
    }

    /**
     * Dates and passenger count handed over from the showroom search strip. Only
     * well-formed values survive, so the wizard never gets arbitrary input.
     *
     * @return array{pickup_date: string, return_date: string, passengers: string}
     */
    private function reservationPrefill(Request $request): array
    {
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) === 1 ? (string) $request->query($key) : '';
        $passengers = (int) $request->query('passengers', 0);

        return [
            'pickup_date' => $date('pickup_date'),
            'return_date' => $date('return_date'),
            'passengers' => $passengers >= 1 && $passengers <= 20 ? (string) $passengers : '',
        ];
    }

    /**
     * One group of customer-facing wording from lang/{locale}/stage.php.
     *
     * @return array<string, mixed>
     */
    private function lines(string $group, string $locale): array
    {
        return trans("stage.{$group}", [], $locale);
    }

    /**
     * @return array{0: Rental, 1: string}
     */
    private function resolveStage(Request $request, string $rentalSlug): array
    {
        $rental = Rental::where('slug', $rentalSlug)->firstOrFail();

        abort_if(! $rental->isPublished(), 404);

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : $rental->default_locale;

        return [$rental, $locale];
    }

    /**
     * Everything the shared stage shell needs, for whichever scene the customer
     * has walked into.
     *
     * @return array<string, mixed>
     */
    private function stageData(Rental $rental, string $locale, string $scene): array
    {
        $vehicles = $rental->vehicles()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->values();

        $services = $rental->knowledgeItems()->where('is_active', true)
            ->whereIn('category', ['services', 'packages', 'delivery'])
            ->orderBy('sort_order')->get()->values();

        $labels = $this->lines('labels', $locale);
        $showroom = $this->lines('showroom', $locale);

        return [
            'rental' => $rental,
            'vehicles' => $vehicles,
            'locale' => $locale,
            'scene' => $scene,
            'menuItems' => $this->stageMenu($rental, $locale, $labels, $showroom),
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'labels' => $labels,
            'showroom' => $showroom,
            'site' => $this->lines('site', $locale),
            'vehicleTerms' => $this->lines('vehicle_terms', $locale),
            'wizard' => [...trans('reservation.messages', [], $locale), ...$this->lines('wizard', $locale)],
            'narration' => $this->lines('narration', $locale),
            'vehicleNarrations' => $this->vehicleNarrations($rental, $vehicles, $locale),
            'staffLinks' => $this->handover->forStaff($rental, $locale),
            'today' => now($rental->timezone)->toDateString(),
            'infoItems' => $rental->knowledgeItems()->where('is_active', true)
                ->whereIn('category', ['general', 'policies', 'faq'])
                ->orderBy('sort_order')->get()->groupBy('category'),
            'services' => $services,
            'sceneNarrations' => $this->sceneNarrations($rental, $services, $locale),
        ];
    }

    /**
     * The main menu. Sections that live on another page are marked so the
     * stage can fade out, as if the agent were walking the customer over.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $showroom
     * @return list<array{key: string, label: string, href: string, exit: bool, tour: ?string, topic: string}>
     */
    private function stageMenu(Rental $rental, string $locale, array $labels, array $showroom): array
    {
        $vehiclesUrl = route('rental.vehicles', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $tour = $this->lines('narration', $locale);

        $servicesUrl = route('rental.services', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $infoUrl = route('rental.info', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $reservationUrl = route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $staffUrl = route('rental.staff', ['rentalSlug' => $rental->slug, 'lang' => $locale]);

        return [
            ['key' => 'vehicles', 'label' => $labels['vehicles_heading'], 'href' => $vehiclesUrl, 'exit' => true, 'tour' => $tour['tour_vehicles'], 'topic' => $labels['menu_vehicles_q']],
            ['key' => 'services', 'label' => $labels['menu_services'], 'href' => $servicesUrl, 'exit' => true, 'tour' => $tour['tour_services'], 'topic' => $labels['menu_services_q']],
            ['key' => 'info', 'label' => $showroom['menu_info'], 'href' => $infoUrl, 'exit' => true, 'tour' => $tour['tour_info'], 'topic' => $labels['menu_policies_q']],
            ['key' => 'reservation', 'label' => $showroom['reservation'], 'href' => $reservationUrl, 'exit' => true, 'tour' => $tour['tour_reservation'], 'topic' => $showroom['reservation_q']],
            ['key' => 'staff', 'label' => $labels['menu_staff'], 'href' => $staffUrl, 'exit' => true, 'tour' => $tour['tour_staff'], 'topic' => $labels['menu_staff_q']],
        ];
    }

    /**
     * Intros for the services list and the rental information scene, built
     * only from stored rental data.
     *
     * @param  Collection<int, RentalKnowledgeItem>  $services
     * @return array{services: ?string, info: string}
     */
    private function sceneNarrations(Rental $rental, Collection $services, string $locale): array
    {
        $templates = $this->lines('narration', $locale);
        $separator = $locale === 'ja' ? '、' : '; ';
        $time = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $intro = null;

        if ($services->isNotEmpty()) {
            $examples = $services->take(3)->map(fn ($item) => $item->translatedTitle($locale))->implode($separator);
            $intro = str_replace(
                [':count', ':examples'],
                [(string) $services->count(), $examples],
                $templates[$services->count() === 1 ? 'services_one' : 'services']
            );
        }

        $location = collect([$rental->city, $rental->country])->filter()->implode($locale === 'ja' ? '、' : ', ');
        $parts = [str_replace(':rental', $rental->name, $templates['info_welcome'])];

        if ($location !== '') {
            $parts[] = str_replace(':location', $location, $templates['info_place']);
        }

        if ($time($rental->open_time) && $time($rental->close_time)) {
            $parts[] = str_replace([':in', ':out'], [$time($rental->open_time), $time($rental->close_time)], $templates['info_hours']);
        }

        $parts[] = $templates['info_more'];

        return ['services' => $intro, 'info' => implode(' ', $parts)];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array{vehicles: ?string, vehicle: array<string, string>}
     */
    private function vehicleNarrations(Rental $rental, Collection $vehicles, string $locale): array
    {
        $templates = $this->lines('narration', $locale);
        $terms = $this->lines('vehicle_terms', $locale);
        $money = fn ($value) => $rental->currency.' '.number_format((float) $value, 0, ',', '.');
        $sentence = fn (string $text) => preg_match('/[.!?。！？]$/u', $text) ? $text : $text.($locale === 'ja' ? '。' : '.');

        if ($vehicles->isEmpty()) {
            return ['vehicles' => null, 'vehicle' => []];
        }

        $intro = str_replace(
            [':count', ':price'],
            [(string) $vehicles->count(), $money($vehicles->min('base_price'))],
            $templates[$vehicles->count() === 1 ? 'vehicles_one' : 'vehicles']
        );

        $perVehicle = $vehicles->mapWithKeys(function ($vehicle) use ($templates, $terms, $money, $sentence, $locale) {
            $parts = [$vehicle->translatedName($locale).'.', filled($vehicle->translatedDescription($locale)) ? $sentence(trim($vehicle->translatedDescription($locale))) : null];

            $parts[] = $vehicle->luggage_capacity
                ? str_replace([':seats', ':luggage'], [(string) $vehicle->maxPassengers(), (string) $vehicle->luggage_capacity], $templates['seats_luggage'])
                : str_replace(':seats', (string) $vehicle->maxPassengers(), $templates['seats']);

            $parts[] = $sentence(implode(', ', array_filter([
                $terms['transmission'][$vehicle->transmission] ?? Str::headline((string) $vehicle->transmission),
                $terms['fuel'][$vehicle->fuel_type] ?? Str::headline((string) $vehicle->fuel_type),
            ])));

            if ($vehicle->insurance_included) {
                $parts[] = $templates['insurance'];
            }

            $parts[] = str_replace(':price', $money($vehicle->base_price), $templates['price']);

            return [$vehicle->slug => implode(' ', array_filter($parts))];
        })->all();

        return ['vehicles' => $intro, 'vehicle' => $perVehicle];
    }
}
