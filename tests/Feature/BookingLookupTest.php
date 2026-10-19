<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use App\Notifications\BookingCancelledByCustomer;
use App\Notifications\BookingStatusChanged;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingLookupTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Vehicle $vehicle;

    private Booking $booking;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'timezone' => 'UTC', 'default_locale' => 'en']);
        $this->vehicle = $this->rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 100, 'seats' => 4, 'is_active' => true]);

        foreach ([5, 6] as $offset) {
            VehicleInventory::create(['vehicle_id' => $this->vehicle->id, 'rental_date' => now('UTC')->addDays($offset)->toDateString(), 'total_units' => 1, 'booked_units' => 0, 'price' => 100]);
        }

        $this->staff = User::factory()->create();
        $this->rental->users()->attach($this->staff->id, ['role' => 'staff', 'status' => 'active']);

        Notification::fake();
        $this->booking = app(ReservationService::class)->createRequest($this->rental, $this->vehicle, [
            'pickup_date' => now('UTC')->addDays(5)->toImmutable()->startOfDay(),
            'return_date' => now('UTC')->addDays(7)->toImmutable()->startOfDay(),
            'passengers' => 2,
            'customer_name' => 'Guest',
            'customer_email' => 'Guest@Example.com',
            'customer_phone' => null,
        ]);
        $this->booking->update(['customer_phone' => '+62 812-3456-7890']);
    }

    /**
     * @return array<string, string>
     */
    private function credentials(string $contact = 'guest@example.com', ?string $reference = null): array
    {
        return ['reference' => $reference ?? $this->booking->reference, 'contact' => $contact, 'lang' => 'en'];
    }

    public function test_the_page_renders_in_every_language(): void
    {
        foreach (['en', 'id', 'ja'] as $lang) {
            $this->get("/demo/booking?lang={$lang}")->assertOk()->assertSee(trans('booking.title', [], $lang));
        }
    }

    public function test_a_customer_sees_their_booking_with_the_email_or_the_phone_they_gave(): void
    {
        $this->post('/demo/booking/lookup', $this->credentials())->assertRedirect('/demo/booking?lang=en');
        $this->get('/demo/booking?lang=en')->assertOk()->assertSee($this->booking->reference)->assertSee('City Car')->assertSee('Waiting for confirmation');

        $this->post('/demo/booking/lookup', $this->credentials('+62 812 3456 7890', strtolower($this->booking->reference)));
        $this->get('/demo/booking?lang=en')->assertOk()->assertSee($this->booking->reference);
    }

    public function test_a_wrong_contact_or_reference_reveals_nothing(): void
    {
        $this->post('/demo/booking/lookup', $this->credentials('someone@else.com'));
        $this->get('/demo/booking?lang=en')->assertOk()->assertSee('could not find a booking')->assertDontSee('City Car');

        $this->post('/demo/booking/lookup', $this->credentials('guest@example.com', 'BK-NOPE00'));
        $this->get('/demo/booking?lang=en')->assertOk()->assertSee('could not find a booking');
    }

    public function test_a_short_digit_string_never_matches_a_phone_number(): void
    {
        $this->assertFalse($this->booking->contactMatches('123'));
        $this->assertFalse($this->booking->contactMatches('   '));
    }

    public function test_a_booking_of_another_rental_is_not_found(): void
    {
        Rental::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);

        $this->post('/other/booking/lookup', $this->credentials());
        $this->get('/other/booking')->assertOk()->assertSee('Cek booking saya')->assertDontSee($this->booking->reference);
    }

    public function test_a_customer_can_cancel_and_the_car_is_released(): void
    {
        $this->assertSame(1, $this->vehicle->inventory()->first()->booked_units);
        Notification::fake();

        $this->post('/demo/booking/cancel', $this->credentials())->assertRedirect('/demo/booking?lang=en');

        $this->assertSame(Booking::STATUS_CANCELLED, $this->booking->fresh()->status);
        $this->assertSame(0, $this->vehicle->inventory()->first()->booked_units);
        Notification::assertSentTo($this->staff, BookingCancelledByCustomer::class);
        Notification::assertSentOnDemand(BookingStatusChanged::class);

        $this->get('/demo/booking?lang=en')->assertSee('has been cancelled');
    }

    public function test_cancelling_with_the_wrong_contact_changes_nothing(): void
    {
        $this->post('/demo/booking/cancel', $this->credentials('someone@else.com'));

        $this->assertSame(Booking::STATUS_PENDING, $this->booking->fresh()->status);
        $this->assertSame(1, $this->vehicle->inventory()->first()->booked_units);
    }

    public function test_a_cancelled_booking_cannot_be_cancelled_twice_or_release_units_again(): void
    {
        $this->post('/demo/booking/cancel', $this->credentials());
        $this->post('/demo/booking/cancel', $this->credentials());

        $this->assertSame(0, $this->vehicle->inventory()->first()->booked_units);
        $this->get('/demo/booking?lang=en')->assertSee('can no longer be cancelled online');
    }

    public function test_a_trip_that_already_started_cannot_be_cancelled_online(): void
    {
        $this->booking->update(['pickup_date' => now('UTC')->subDay()->toDateString(), 'status' => Booking::STATUS_CONFIRMED]);

        $this->post('/demo/booking/cancel', $this->credentials());

        $this->assertSame(Booking::STATUS_CONFIRMED, $this->booking->fresh()->status);
    }
}
