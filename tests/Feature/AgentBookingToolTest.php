<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Rental;
use App\Models\VehicleInventory;
use App\Services\Agent\RentalAgentTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentBookingToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_booking_tool_creates_a_reference_and_holds_the_requested_cars(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $vehicle = $rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1000000, 'seats' => 2, 'is_active' => true]);
        $conversation = Conversation::create(['rental_id' => $rental->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);

        $pickupDate = CarbonImmutable::now()->addDays(5);
        foreach ([0, 1] as $offset) {
            VehicleInventory::create(['vehicle_id' => $vehicle->id, 'rental_date' => $pickupDate->addDays($offset)->toDateString(), 'total_units' => 3, 'booked_units' => 0, 'price' => 1000000]);
        }

        $tools = new RentalAgentTools($rental, $conversation, 'en');
        $input = [
            'vehicle_slug' => 'city-car', 'pickup_date' => $pickupDate->toDateString(), 'return_date' => $pickupDate->addDays(2)->toDateString(),
            'passengers' => 2, 'customer_name' => 'Ayu', 'customer_phone' => '+62 811 111 222',
        ];

        $quote = $tools->dispatch('check_availability', [...$input, 'with_driver' => false]);
        $this->assertSame(2000000.0, $quote['ui']['quote']['grand_total']);

        $result = $tools->dispatch('create_booking_request', [...$input, 'units' => 2]);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $result['ui']['booking']['booking_reference']);
        $this->assertSame(2, $booking->unit_count);
        $this->assertSame(4000000.0, (float) $booking->total_price);
        $this->assertSame([2, 2], VehicleInventory::orderBy('rental_date')->pluck('booked_units')->all());

        $tooMany = $tools->dispatch('create_booking_request', [...$input, 'units' => 2]);
        $this->assertNull($tooMany['ui']);
        $this->assertSame(1, Booking::count());
    }
}
