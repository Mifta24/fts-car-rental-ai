<?php

namespace App\Services\Agent;

use App\Exceptions\PricingOptionRejected;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Rental;
use App\Models\RentalKnowledgeItem;
use App\Models\Vehicle;
use App\Services\Reservation\BookingNotifier;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Executes the AI Rental Agent's tools against this rental company's controlled data.
 * Every rental fact, vehicle, price and availability answer must pass through
 * here — the model itself is never trusted to hold any of it.
 */
class RentalAgentTools
{
    public function __construct(
        private readonly Rental $rental,
        private readonly Conversation $conversation,
        private readonly string $locale,
        private readonly ReservationService $reservations = new ReservationService,
        private readonly BookingNotifier $notifier = new BookingNotifier,
    ) {}

    /**
     * OpenAI-compatible function-calling schema (used by the local LM Studio
     * / Ollama endpoint via AgentService — see topic in that class).
     */
    public static function definitions(): array
    {
        return array_map(
            fn (array $tool) => ['type' => 'function', 'function' => $tool],
            [
                [
                    'name' => 'search_knowledge',
                    'description' => 'Search the rental company\'s approved knowledge base for facts: rental requirements, policies, services (driver, delivery, add-ons), packages, delivery and airport pick-up, and FAQs. Always use this instead of answering rental-fact questions from memory. Returns up to 5 matching entries.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Keywords from the customer question, e.g. "deposit" or "airport delivery"'],
                            'category' => [
                                'type' => 'string',
                                'enum' => ['general', 'services', 'policies', 'packages', 'delivery', 'faq'],
                                'description' => 'Optional category filter',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
                [
                    'name' => 'search_vehicles',
                    'description' => 'Search cars that fit the customer\'s pick-up and return dates and passenger count, with real-time availability and total price already checked. Use this whenever a customer describes what they want (dates, passengers, body type, budget) rather than naming one car by name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pickup_date' => ['type' => 'string', 'description' => 'Pick-up date, YYYY-MM-DD'],
                            'return_date' => ['type' => 'string', 'description' => 'Return date, YYYY-MM-DD'],
                            'passengers' => ['type' => 'integer', 'minimum' => 1],
                            'body_type' => ['type' => 'string', 'description' => 'e.g. suv, mpv, sedan, hatchback, van, luxury — optional preference'],
                            'transmission' => ['type' => 'string', 'enum' => ['automatic', 'manual'], 'description' => 'Optional preference'],
                        ],
                        'required' => ['pickup_date', 'return_date', 'passengers'],
                    ],
                ],
                [
                    'name' => 'get_vehicle_detail',
                    'description' => 'Get full details and photos for one car by its slug (from a previous search_vehicles result). Use this when the customer asks to see more about, or see photos of, a specific car.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'vehicle_slug' => ['type' => 'string'],
                            'image_tag' => ['type' => 'string', 'description' => 'Optional filter, e.g. "interior", "exterior", "trunk", "dashboard"'],
                        ],
                        'required' => ['vehicle_slug'],
                    ],
                ],
                [
                    'name' => 'get_pricing_options',
                    'description' => 'List the optional extras (child seat, GPS, delivery, …) with their ids and prices, and the long-rental discounts this rental offers. Call this when the customer asks about extras, add-ons, delivery or discounts. Promo codes are never listed: only the customer can supply one.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                ],
                [
                    'name' => 'check_availability',
                    'description' => 'Get the current, real-time availability and exact total price for one car over specific dates. ALWAYS call this before confirming a price or telling a customer a car is available — never state a price or availability from memory.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'vehicle_slug' => ['type' => 'string'],
                            'pickup_date' => ['type' => 'string'],
                            'return_date' => ['type' => 'string'],
                            'with_driver' => ['type' => 'boolean', 'description' => 'Whether the customer wants a driver included'],
                            'units' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of cars, default 1'],
                            'addon_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Ids of extras the customer chose, from get_pricing_options'],
                            'promo_code' => ['type' => 'string', 'description' => 'A promo code the customer gave you. Never invent one.'],
                        ],
                        'required' => ['vehicle_slug', 'pickup_date', 'return_date'],
                    ],
                ],
                [
                    'name' => 'create_booking_request',
                    'description' => 'Create a rental request after the customer confirms the car and dates and you have their name and phone. This re-checks availability before booking. It does not charge payment — it creates a pending reservation for the rental team to confirm.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'vehicle_slug' => ['type' => 'string'],
                            'pickup_date' => ['type' => 'string'],
                            'return_date' => ['type' => 'string'],
                            'passengers' => ['type' => 'integer'],
                            'units' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of cars, default 1'],
                            'with_driver' => ['type' => 'boolean'],
                            'addon_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Ids of extras the customer chose, from get_pricing_options'],
                            'promo_code' => ['type' => 'string', 'description' => 'A promo code the customer gave you. Never invent one.'],
                            'customer_name' => ['type' => 'string'],
                            'customer_email' => ['type' => 'string'],
                            'customer_phone' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['vehicle_slug', 'pickup_date', 'return_date', 'passengers', 'customer_name', 'customer_phone'],
                    ],
                ],
                [
                    'name' => 'request_human_handover',
                    'description' => 'Hand this conversation over to a human member of the rental team. Use this for special requests, complaints, corporate or fleet bookings, negotiated rates, unusual cancellations, payment problems, accidents or breakdowns, or any question you cannot answer confidently from the available tools. Always write a clear summary so staff do not need to ask the customer to repeat themselves.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'enum' => ['special_request', 'complaint', 'corporate_booking', 'negotiated_rate', 'unusual_cancellation', 'payment_issue', 'breakdown_incident', 'low_confidence'],
                            ],
                            'summary' => ['type' => 'string', 'description' => 'What the customer wants and the relevant context gathered so far, written for a staff member who has not seen this conversation'],
                        ],
                        'required' => ['reason', 'summary'],
                    ],
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{text: string, ui: array|null}
     */
    public function dispatch(string $name, array $input): array
    {
        return match ($name) {
            'search_knowledge' => $this->searchKnowledge($input),
            'search_vehicles' => $this->searchVehicles($input),
            'get_vehicle_detail' => $this->getVehicleDetail($input),
            'get_pricing_options' => $this->getPricingOptions(),
            'check_availability' => $this->checkAvailability($input),
            'create_booking_request' => $this->createBookingRequest($input),
            'request_human_handover' => $this->requestHumanHandover($input),
            default => ['text' => "Unknown tool: {$name}", 'ui' => null],
        };
    }

    private function searchKnowledge(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        $category = $input['category'] ?? null;
        $words = collect(preg_split('/\s+/', Str::lower($query)))->filter();

        $items = $this->rental->knowledgeItems()
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->get()
            ->filter(function (RentalKnowledgeItem $item) use ($query, $words) {
                if ($query === '') {
                    return true;
                }

                $haystack = Str::lower($item->title.' '.$item->body.' '.implode(' ', $item->tags ?? []));

                return Str::contains($haystack, Str::lower($query))
                    || $words->contains(fn ($word) => Str::contains($haystack, $word));
            })
            ->take(5);

        if ($items->isEmpty()) {
            return [
                'text' => 'No matching knowledge base entry was found. Do not guess the answer — tell the customer you will check with the team, or call request_human_handover.',
                'ui' => null,
            ];
        }

        $results = $items->map(fn (RentalKnowledgeItem $item) => [
            'category' => $item->category,
            'title' => $item->translatedTitle($this->locale),
            'body' => $item->translatedBody($this->locale),
        ])->values()->all();

        return ['text' => json_encode($results, JSON_UNESCAPED_UNICODE), 'ui' => null];
    }

    private function searchVehicles(array $input): array
    {
        [$pickupDate, $returnDate, $days] = $this->parseTrip($input['pickup_date'], $input['return_date']);
        $passengers = (int) $input['passengers'];
        $bodyType = $input['body_type'] ?? null;
        $transmission = $input['transmission'] ?? null;

        if ($days < 1) {
            return ['text' => 'return_date must be after pickup_date.', 'ui' => null];
        }

        $candidates = $this->rental->vehicles()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Vehicle $vehicle) => $this->reservations->fitsPassengers($vehicle, $passengers, 1))
            ->when($bodyType, fn ($c) => $c->filter(fn (Vehicle $vehicle) => $vehicle->body_type === $bodyType))
            ->when($transmission, fn ($c) => $c->filter(fn (Vehicle $vehicle) => $vehicle->transmission === $transmission));

        $matches = [];
        foreach ($candidates as $vehicle) {
            $trip = $this->reservations->quote($vehicle, $pickupDate, $returnDate);
            if (! $trip) {
                continue;
            }

            $thumbnail = $vehicle->images->first();

            $matches[] = [
                'vehicle_slug' => $vehicle->slug,
                'name' => $vehicle->translatedName($this->locale),
                'model_year' => $vehicle->model_year,
                'body_type' => $vehicle->body_type,
                'transmission' => $vehicle->transmission,
                'fuel_type' => $vehicle->fuel_type,
                'seats' => $vehicle->seats,
                'luggage_capacity' => $vehicle->luggage_capacity,
                'insurance_included' => $vehicle->insurance_included,
                'days' => $days,
                'total_price' => $trip['vehicle_total'],
                'currency' => $this->rental->currency,
                'min_available_units' => $trip['min_available_units'],
                'thumbnail_url' => $thumbnail?->image_source,
            ];
        }

        if (empty($matches)) {
            return [
                'text' => 'No car has availability for every day of that rental for this passenger count. Tell the customer honestly and offer to check different dates, or call request_human_handover if they want to wait-list.',
                'ui' => null,
            ];
        }

        usort($matches, fn ($a, $b) => $a['total_price'] <=> $b['total_price']);

        return [
            'text' => json_encode($matches, JSON_UNESCAPED_UNICODE),
            'ui' => [
                'type' => 'vehicle_results',
                'pickup_date' => $input['pickup_date'],
                'return_date' => $input['return_date'],
                'vehicles' => $matches,
            ],
        ];
    }

    private function getVehicleDetail(array $input): array
    {
        $vehicle = $this->findVehicle($input['vehicle_slug']);
        if (! $vehicle) {
            return ['text' => 'Vehicle not found.', 'ui' => null];
        }

        $images = $vehicle->images;
        $tag = $input['image_tag'] ?? null;
        if ($tag) {
            $images = $images->filter(fn ($img) => $img->hasTag($tag));
        }

        $detail = [
            'vehicle_slug' => $vehicle->slug,
            'name' => $vehicle->translatedName($this->locale),
            'description' => $vehicle->translatedDescription($this->locale),
            'model_year' => $vehicle->model_year,
            'body_type' => $vehicle->body_type,
            'transmission' => $vehicle->transmission,
            'fuel_type' => $vehicle->fuel_type,
            'seats' => $vehicle->seats,
            'luggage_capacity' => $vehicle->luggage_capacity,
            'insurance_included' => $vehicle->insurance_included,
            'driver_available' => $vehicle->driver_available,
            'driver_price' => $vehicle->driver_price,
            'features' => $vehicle->features,
            'starting_price' => $vehicle->base_price,
            'currency' => $this->rental->currency,
            'note' => 'starting_price is a per-day indicative rate only — always call check_availability for the exact price on real dates.',
            'images' => $images->map(fn ($img) => [
                'url' => $img->image_source,
                'tags' => $img->tags,
                'alt' => $img->alt_text,
            ])->values()->all(),
        ];

        return [
            'text' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'vehicle_detail', 'vehicle' => $detail],
        ];
    }

    private function checkAvailability(array $input): array
    {
        $vehicle = $this->findVehicle($input['vehicle_slug']);
        if (! $vehicle) {
            return ['text' => 'Vehicle not found.', 'ui' => null];
        }

        [$pickupDate, $returnDate, $days] = $this->parseTrip($input['pickup_date'], $input['return_date']);
        if ($days < 1) {
            return ['text' => 'return_date must be after pickup_date.', 'ui' => null];
        }

        $withDriver = (bool) ($input['with_driver'] ?? false);

        try {
            $trip = $this->reservations->quote(
                $vehicle, $pickupDate, $returnDate, max(1, (int) ($input['units'] ?? 1)), $withDriver,
                addonIds: $this->addonIds($input), promoCode: $input['promo_code'] ?? null, locale: $this->locale,
            );
        } catch (PricingOptionRejected $rejected) {
            return $this->rejectedOption($rejected);
        }

        if (! $trip) {
            return [
                'text' => json_encode([
                    'available' => false,
                    'vehicle_slug' => $vehicle->slug,
                    'message' => 'Not every day in this range has availability for this car.',
                ], JSON_UNESCAPED_UNICODE),
                'ui' => ['type' => 'availability', 'available' => false, 'vehicle_slug' => $vehicle->slug],
            ];
        }

        $result = [
            'available' => true,
            'vehicle_slug' => $vehicle->slug,
            'name' => $vehicle->translatedName($this->locale),
            'pickup_date' => $pickupDate->toDateString(),
            'return_date' => $returnDate->toDateString(),
            'days' => $days,
            'daily_breakdown' => $trip['daily'],
            'vehicle_total' => $trip['vehicle_total'],
            'driver_total' => $trip['driver_total'],
            'extras' => $trip['addons'],
            'extras_total' => $trip['addons_total'],
            'long_rental_discount' => $trip['duration_discount'],
            'promo' => $trip['promo'],
            'grand_total' => $trip['grand_total'],
            'currency' => $this->rental->currency,
            'min_available_units' => $trip['min_available_units'],
        ];

        return [
            'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'availability', 'available' => true, 'quote' => $result],
        ];
    }

    private function createBookingRequest(array $input): array
    {
        $vehicle = $this->findVehicle($input['vehicle_slug']);
        if (! $vehicle) {
            return ['text' => 'Vehicle not found.', 'ui' => null];
        }

        [$pickupDate, $returnDate, $days] = $this->parseTrip($input['pickup_date'], $input['return_date']);
        if ($days < 1) {
            return ['text' => 'return_date must be after pickup_date.', 'ui' => null];
        }

        $units = max(1, (int) ($input['units'] ?? 1));

        if (! $this->reservations->fitsPassengers($vehicle, (int) $input['passengers'], $units)) {
            return ['text' => 'This party does not fit that car for the requested number of units. Suggest a larger car or more units.', 'ui' => null];
        }

        try {
            $booking = $this->reservations->createRequest($this->rental, $vehicle, [
                'pickup_date' => $pickupDate,
                'return_date' => $returnDate,
                'passengers' => (int) $input['passengers'],
                'units' => $units,
                'with_driver' => (bool) ($input['with_driver'] ?? false),
                'addon_ids' => $this->addonIds($input),
                'promo_code' => $input['promo_code'] ?? null,
                'customer_name' => $input['customer_name'],
                'customer_email' => $input['customer_email'] ?? null,
                'customer_phone' => $input['customer_phone'],
                'notes' => $input['notes'] ?? null,
            ], $this->conversation);
        } catch (PricingOptionRejected $rejected) {
            return $this->rejectedOption($rejected);
        }

        if (! $booking) {
            return [
                'text' => 'The car is no longer available for these exact dates — availability may have just changed. Call check_availability again or offer alternative dates.',
                'ui' => null,
            ];
        }

        $payload = [
            'booking_reference' => $booking->reference,
            'vehicle_slug' => $vehicle->slug,
            'name' => $vehicle->translatedName($this->locale),
            'pickup_date' => $pickupDate->toDateString(),
            'return_date' => $returnDate->toDateString(),
            'days' => $days,
            'units' => $units,
            'extras' => $booking->addons ?? [],
            'promo_code' => $booking->promo_code,
            'discount_total' => (float) $booking->duration_discount + (float) $booking->promo_discount,
            'total_price' => (float) $booking->total_price,
            'currency' => $this->rental->currency,
            'status' => 'pending_confirmation',
        ];

        return [
            'text' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'booking_confirmation', 'booking' => $payload],
        ];
    }

    private function requestHumanHandover(array $input): array
    {
        $reason = $input['reason'];
        $summary = $input['summary'];

        $handover = HandoverRequest::create([
            'conversation_id' => $this->conversation->id,
            'reason' => $reason,
            'summary' => $summary,
            'status' => HandoverRequest::STATUS_OPEN,
        ]);

        $this->conversation->update([
            'status' => Conversation::STATUS_HANDED_OVER,
            'handover_summary' => $summary,
        ]);

        $this->notifier->handoverRequested($handover);

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A staff member has been notified and will join this conversation shortly.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'handover', 'reason' => $reason, 'summary' => $summary],
        ];
    }

    private function getPricingOptions(): array
    {
        $payload = [
            'currency' => $this->rental->currency,
            'extras' => $this->rental->addons()->where('is_active', true)->get()->map(fn ($addon) => [
                'id' => $addon->id,
                'name' => $addon->translatedName($this->locale),
                'price' => (float) $addon->price,
                'charged' => $addon->charge === 'per_day' ? 'per day, per car' : 'once per booking',
            ])->all(),
            'long_rental_discounts' => $this->rental->durationDiscounts()->get()->map(fn ($discount) => [
                'from_days' => $discount->min_days,
                'percent_off_car_price' => (float) $discount->percent,
            ])->all(),
        ];

        return ['text' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'ui' => null];
    }

    // --- helpers ---

    /**
     * @param  array<string, mixed>  $input
     * @return list<int>
     */
    private function addonIds(array $input): array
    {
        return array_values(array_filter(array_map('intval', (array) ($input['addon_ids'] ?? []))));
    }

    /**
     * @return array{text: string, ui: null}
     */
    private function rejectedOption(PricingOptionRejected $rejected): array
    {
        return [
            'text' => json_encode([
                'applied' => false,
                'problem' => trans('reservation.messages', [], $this->locale)[$rejected->reason],
                'instruction' => 'Tell the customer plainly and continue without this option unless they give a different one. Never guess or invent a code or extra.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => null,
        ];
    }

    private function findVehicle(string $slug): ?Vehicle
    {
        return $this->rental->vehicles()->where('slug', $slug)->first();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function parseTrip(string $pickupDate, string $returnDate): array
    {
        $out = CarbonImmutable::parse($pickupDate)->startOfDay();
        $back = CarbonImmutable::parse($returnDate)->startOfDay();

        return [$out, $back, $out->diffInDays($back)];
    }
}
