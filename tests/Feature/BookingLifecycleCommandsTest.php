<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Rental;
use App\Models\VehicleInventory;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingLifecycleCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
    }

    private function pendingBooking(int $hoursOld): Booking
    {
        $vehicle = $this->rental->vehicles()->create(['name' => 'Car '.Str::random(4), 'slug' => Str::random(8), 'base_price' => 1, 'seats' => 4, 'is_active' => true]);
        VehicleInventory::create(['vehicle_id' => $vehicle->id, 'rental_date' => now()->addDays(10)->toDateString(), 'total_units' => 1, 'booked_units' => 1, 'price' => 1]);

        $booking = Booking::create([
            'reference' => 'BK-'.strtoupper(Str::random(6)), 'rental_id' => $this->rental->id, 'vehicle_id' => $vehicle->id, 'customer_name' => 'Guest',
            'pickup_date' => now()->addDays(10)->toDateString(), 'return_date' => now()->addDays(11)->toDateString(), 'total_price' => 1,
        ]);
        $booking->forceFill(['created_at' => now()->subHours($hoursOld)])->save();

        return $booking;
    }

    public function test_pending_requests_nobody_answered_expire_and_give_their_car_back(): void
    {
        $stale = $this->pendingBooking(ReservationService::PENDING_HOLD_HOURS + 1);
        $fresh = $this->pendingBooking(1);

        $this->artisan('bookings:expire-pending')->expectsOutput('Expired 1 pending booking(s).')->assertSuccessful();

        $this->assertSame(Booking::STATUS_EXPIRED, $stale->fresh()->status);
        $this->assertSame(0, VehicleInventory::where('vehicle_id', $stale->vehicle_id)->value('booked_units'));
        $this->assertSame(Booking::STATUS_PENDING, $fresh->fresh()->status);
        $this->assertSame(1, VehicleInventory::where('vehicle_id', $fresh->vehicle_id)->value('booked_units'));
    }

    public function test_confirmed_bookings_never_expire(): void
    {
        $booking = $this->pendingBooking(ReservationService::PENDING_HOLD_HOURS + 100);
        $booking->update(['status' => Booking::STATUS_CONFIRMED]);

        $this->artisan('bookings:expire-pending')->assertSuccessful();

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_idle_conversations_are_deleted_but_active_and_open_handover_ones_stay(): void
    {
        $make = fn (int $daysIdle) => Conversation::create(['rental_id' => $this->rental->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en', 'last_message_at' => now()->subDays($daysIdle)]);

        $old = $make(120);
        $recent = $make(5);
        $oldWithOpenHandover = $make(120);
        HandoverRequest::create(['conversation_id' => $oldWithOpenHandover->id, 'reason' => 'complaint', 'summary' => 'Open']);
        $old->messages()->create(['role' => 'customer', 'content' => 'hi']);

        $this->artisan('conversations:prune')->expectsOutput('Deleted 1 stale conversation(s).')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($oldWithOpenHandover);
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_pruning_keeps_the_booking_made_in_a_deleted_conversation(): void
    {
        $booking = $this->pendingBooking(1);
        $conversation = Conversation::create(['rental_id' => $this->rental->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en', 'last_message_at' => now()->subDays(200)]);
        $booking->update(['conversation_id' => $conversation->id]);

        $this->artisan('conversations:prune')->assertSuccessful();

        $this->assertNull($booking->fresh()->conversation_id);
    }
}
