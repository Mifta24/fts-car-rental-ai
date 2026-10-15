<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationRequestTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Vehicle $cityCar;

    private Vehicle $familyVan;

    private string $pickupDate;

    private string $returnDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'timezone' => 'Asia/Makassar',
            'default_locale' => 'en', 'whatsapp' => '+62 812-3456-7890', 'phone' => '+62 361 771234', 'email' => 'front@demo.test',
        ]);

        $this->cityCar = $this->rental->vehicles()->create([
            'name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1000000, 'seats' => 4,
            'driver_available' => true, 'driver_price' => 250000, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->familyVan = $this->rental->vehicles()->create([
            'name' => 'Family Van', 'slug' => 'family-van', 'base_price' => 2000000, 'seats' => 7,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        $this->pickupDate = $today->addDays(10)->toDateString();
        $this->returnDate = $today->addDays(13)->toDateString();

        foreach ([$this->cityCar->id => [2, 1000000], $this->familyVan->id => [1, 2000000]] as $vehicleId => [$units, $price]) {
            foreach (range(10, 12) as $offset) {
                VehicleInventory::create([
                    'vehicle_id' => $vehicleId, 'rental_date' => $today->addDays($offset)->toDateString(),
                    'total_units' => $units, 'booked_units' => 0, 'price' => $price,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function trip(array $overrides = []): array
    {
        return [
            'vehicle_slug' => 'city-car', 'pickup_date' => $this->pickupDate, 'return_date' => $this->returnDate,
            'passengers' => 2, 'units' => 1, 'locale' => 'en',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function request(array $overrides = []): array
    {
        return $this->trip([
            'customer_name' => 'John Tan', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111',
            'special_request' => 'Child seat', ...$overrides,
        ]);
    }

    public function test_quote_prices_every_day_for_every_vehicle(): void
    {
        $this->postJson('/demo/reservation/quote', $this->trip(['units' => 2, 'with_driver' => true]))
            ->assertOk()
            ->assertJson([
                'available' => true, 'days' => 3, 'vehicle_total' => 6000000, 'driver_total' => 1500000,
                'grand_total' => 7500000, 'currency' => 'IDR',
            ]);
    }

    public function test_quote_rejects_invalid_dates_in_the_customer_language(): void
    {
        $yesterday = CarbonImmutable::now('Asia/Makassar')->subDay()->toDateString();

        $this->postJson('/demo/reservation/quote', $this->trip(['pickup_date' => $yesterday, 'locale' => 'id']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.pickup_date.0', 'Tanggal pengambilan tidak boleh di masa lalu.');

        $this->postJson('/demo/reservation/quote', $this->trip(['return_date' => $this->pickupDate]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.return_date.0', 'Return date must be after the pick-up date.');
    }

    public function test_quote_rejects_rentals_longer_than_the_limit(): void
    {
        $tooLong = CarbonImmutable::parse($this->pickupDate)->addDays(31)->toDateString();

        $this->postJson('/demo/reservation/quote', $this->trip(['return_date' => $tooLong]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('return_date');
    }

    public function test_quote_rejects_a_party_larger_than_the_car_can_seat(): void
    {
        $this->postJson('/demo/reservation/quote', $this->trip(['passengers' => 5]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.passengers.0', 'This car does not seat that many passengers. Choose a larger car or add more units.');

        $this->postJson('/demo/reservation/quote', $this->trip(['passengers' => 5, 'units' => 2]))->assertOk();
    }

    public function test_quote_rejects_unknown_and_inactive_vehicles(): void
    {
        $this->cityCar->update(['is_active' => false]);

        $this->postJson('/demo/reservation/quote', $this->trip())->assertUnprocessable()->assertJsonValidationErrors('vehicle_slug');
        $this->postJson('/demo/reservation/quote', $this->trip(['vehicle_slug' => 'nope']))->assertUnprocessable()->assertJsonValidationErrors('vehicle_slug');
    }

    public function test_unavailable_car_offers_alternatives_that_fit_the_party(): void
    {
        VehicleInventory::where('vehicle_id', $this->cityCar->id)->update(['booked_units' => 2]);

        $this->postJson('/demo/reservation/quote', $this->trip())
            ->assertUnprocessable()
            ->assertJsonPath('errors.vehicle_slug.0', 'This car is not available for those dates.')
            ->assertJsonPath('alternatives.0.slug', 'family-van')
            ->assertJsonPath('alternatives.0.total', 6000000);
    }

    public function test_quote_asks_for_more_units_than_are_free_when_booking_several_cars(): void
    {
        $this->postJson('/demo/reservation/quote', $this->trip(['vehicle_slug' => 'family-van', 'units' => 2, 'passengers' => 2]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.vehicle_slug.0', 'This car is not available for those dates.');
    }

    public function test_submitting_creates_a_pending_request_holds_inventory_and_returns_handover_links(): void
    {
        $conversation = Conversation::create(['rental_id' => $this->rental->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);

        $response = $this->postJson('/demo/reservation', $this->request(['units' => 2, 'passengers' => 5, 'customer_token' => $conversation->customer_token]))
            ->assertCreated()
            ->assertJsonPath('status', Booking::STATUS_PENDING)
            ->assertJsonPath('total', 6000000)
            ->assertJsonPath('handover.phone_url', 'tel:+62361771234');

        $booking = Booking::firstOrFail();
        $this->assertMatchesRegularExpression('/^BK-[A-Z0-9]{6}$/', $booking->reference);
        $response->assertJsonPath('reference', $booking->reference);
        $this->assertSame(2, $booking->unit_count);
        $this->assertSame('whatsapp', $booking->contact_type);
        $this->assertSame('+62 812 0000 1111', $booking->customer_phone);
        $this->assertNull($booking->customer_email);
        $this->assertSame('Child seat', $booking->notes);
        $this->assertSame($conversation->id, $booking->conversation_id);
        $this->assertSame([2, 2, 2], VehicleInventory::where('vehicle_id', $this->cityCar->id)->orderBy('rental_date')->pluck('booked_units')->all());

        $whatsapp = $response->json('handover.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $whatsapp);
        $message = urldecode(substr($whatsapp, strlen('https://wa.me/6281234567890?text=')));
        $this->assertStringContainsString('Name: John Tan', $message);
        $this->assertStringContainsString('Passengers: 5', $message);
        $this->assertStringContainsString('Units: 2', $message);
        $this->assertStringContainsString('Car: City Car', $message);
        $this->assertStringContainsString('Driver: Self-drive', $message);
        $this->assertStringContainsString('Special Request: Child seat', $message);
        $this->assertStringContainsString("Reference: {$booking->reference}", $message);
        $this->assertStringStartsWith('mailto:front@demo.test?subject=', $response->json('handover.email_url'));
    }

    public function test_email_contacts_are_stored_as_email_and_validated(): void
    {
        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_value.0', 'Please enter a valid email address.');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'phone', 'contact_value' => 'call me']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_value');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'john@example.com']))->assertCreated();

        $booking = Booking::firstOrFail();
        $this->assertSame('john@example.com', $booking->customer_email);
        $this->assertNull($booking->customer_phone);
    }

    public function test_the_last_free_car_can_only_be_requested_once(): void
    {
        $this->postJson('/demo/reservation', $this->request(['vehicle_slug' => 'family-van']))->assertCreated();

        $this->postJson('/demo/reservation', $this->request(['vehicle_slug' => 'family-van', 'customer_name' => 'Late Customer']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.vehicle_slug.0', 'This car is not available for those dates.');

        $this->assertSame(1, Booking::count());
    }

    public function test_reference_is_unique_per_request(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();
        $this->postJson('/demo/reservation', $this->request())->assertCreated();

        $this->assertSame(2, Booking::distinct()->count('reference'));
    }

    public function test_unpublished_rentals_reject_reservations(): void
    {
        Rental::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->postJson('/draft/reservation', $this->request())->assertNotFound();
        $this->postJson('/draft/reservation/quote', $this->trip())->assertNotFound();
    }

    public function test_reservation_requests_are_rate_limited(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->postJson('/demo/reservation/quote', $this->trip())->assertOk();
        }

        $this->postJson('/demo/reservation/quote', $this->trip())->assertTooManyRequests();
    }

    public function test_cancelling_a_multi_unit_booking_releases_every_held_unit(): void
    {
        $this->postJson('/demo/reservation', $this->request(['units' => 2, 'passengers' => 5]))->assertCreated();
        $booking = Booking::firstOrFail();

        $user = User::factory()->create();
        $this->rental->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($user)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame([0, 0, 0], VehicleInventory::where('vehicle_id', $this->cityCar->id)->orderBy('rental_date')->pluck('booked_units')->all());
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }
}
