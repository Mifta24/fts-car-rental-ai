<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingStatusTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->staff = User::factory()->create();
        $rental->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);

        $this->vehicle = $rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1000000, 'seats' => 4, 'is_active' => true]);

        foreach ([10, 11] as $offset) {
            VehicleInventory::create([
                'vehicle_id' => $this->vehicle->id, 'rental_date' => now()->addDays($offset)->toDateString(),
                'total_units' => 1, 'booked_units' => 0, 'price' => 1000000,
            ]);
        }
    }

    private function bookingWithStatus(string $status, int $bookedUnits): Booking
    {
        VehicleInventory::where('vehicle_id', $this->vehicle->id)->update(['booked_units' => $bookedUnits]);

        return Booking::create([
            'reference' => 'BK-'.strtoupper(fake()->unique()->lexify('??????')), 'rental_id' => $this->vehicle->rental_id, 'vehicle_id' => $this->vehicle->id,
            'customer_name' => 'Guest', 'pickup_date' => now()->addDays(10)->toDateString(), 'return_date' => now()->addDays(12)->toDateString(),
            'total_price' => 2000000, 'status' => $status,
        ]);
    }

    private function bookedUnits(): int
    {
        return (int) VehicleInventory::where('vehicle_id', $this->vehicle->id)->max('booked_units');
    }

    public function test_cancelling_a_booking_gives_its_car_back(): void
    {
        $booking = $this->bookingWithStatus(Booking::STATUS_CONFIRMED, 1);

        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])->assertRedirect();

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(0, $this->bookedUnits());
    }

    public function test_cancelling_twice_or_cancelling_an_expired_booking_does_not_release_the_car_again(): void
    {
        $cancelled = $this->bookingWithStatus(Booking::STATUS_CANCELLED, 0);
        $expired = $this->bookingWithStatus(Booking::STATUS_EXPIRED, 0);

        foreach ([$cancelled, $expired] as $booking) {
            $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])->assertRedirect();
        }

        $this->assertSame(0, $this->bookedUnits());
    }

    public function test_reconfirming_a_cancelled_booking_holds_the_car_again(): void
    {
        $booking = $this->bookingWithStatus(Booking::STATUS_CANCELLED, 0);

        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])->assertSessionHas('status');

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        $this->assertSame(1, $this->bookedUnits());
    }

    public function test_a_cancelled_booking_cannot_be_reconfirmed_once_the_car_went_to_someone_else(): void
    {
        $cancelled = $this->bookingWithStatus(Booking::STATUS_CANCELLED, 1);

        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $cancelled), ['status' => Booking::STATUS_CONFIRMED])->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_CANCELLED, $cancelled->fresh()->status);
        $this->assertSame(1, $this->bookedUnits());
    }

    public function test_confirming_a_pending_booking_does_not_hold_the_car_twice(): void
    {
        $booking = $this->bookingWithStatus(Booking::STATUS_PENDING, 1);

        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])->assertRedirect();

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        $this->assertSame(1, $this->bookedUnits());
    }

    public function test_the_list_can_be_filtered_to_expired_bookings(): void
    {
        $this->bookingWithStatus(Booking::STATUS_EXPIRED, 0);

        $this->actingAs($this->staff)->get(route('admin.bookings.index', ['status' => 'expired']))->assertOk()->assertSee('Reopen &amp; confirm', false);
    }
}
