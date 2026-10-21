<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use App\Models\Rental;
use App\Models\RentalUser;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Vehicle $vehicle;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->viewer = User::factory()->create();
        $this->rental->users()->attach($this->viewer->id, ['role' => RentalUser::ROLE_VIEWER, 'status' => 'active']);
        $this->vehicle = $this->rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1000000, 'seats' => 4, 'is_active' => true]);
    }

    private function booking(string $status, float $total, ?Conversation $conversation = null, ?Vehicle $vehicle = null, ?string $createdAt = null): Booking
    {
        $booking = Booking::create([
            'reference' => 'BK-'.strtoupper(Str::random(6)), 'rental_id' => $this->rental->id, 'vehicle_id' => ($vehicle ?? $this->vehicle)->id,
            'conversation_id' => $conversation?->id, 'customer_name' => 'Guest', 'pickup_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(), 'total_price' => $total, 'status' => $status,
        ]);

        if ($createdAt) {
            $booking->forceFill(['created_at' => $createdAt])->save();
        }

        return $booking;
    }

    private function conversation(): Conversation
    {
        return $this->rental->conversations()->create(['customer_token' => (string) Str::uuid(), 'status' => 'active', 'locale' => 'en']);
    }

    public function test_the_headline_numbers_are_computed_for_the_period(): void
    {
        $chatA = $this->conversation();
        $this->conversation();
        $suv = $this->rental->vehicles()->create(['name' => 'Big SUV', 'slug' => 'big-suv', 'base_price' => 1, 'seats' => 7, 'is_active' => true]);

        $this->booking(Booking::STATUS_CONFIRMED, 2000000, $chatA);
        $this->booking(Booking::STATUS_CONFIRMED, 3000000, vehicle: $suv);
        $this->booking(Booking::STATUS_CONFIRMED, 3000000, vehicle: $suv);
        $this->booking(Booking::STATUS_PENDING, 900000);
        $this->booking(Booking::STATUS_CANCELLED, 700000);
        $this->booking(Booking::STATUS_CONFIRMED, 9000000, createdAt: now()->subDays(60)->toDateTimeString());

        VehicleInventory::create(['vehicle_id' => $this->vehicle->id, 'rental_date' => now()->subDay()->toDateString(), 'total_units' => 4, 'booked_units' => 2, 'blocked_units' => 0, 'price' => 1]);
        VehicleInventory::create(['vehicle_id' => $this->vehicle->id, 'rental_date' => now()->toDateString(), 'total_units' => 4, 'booked_units' => 1, 'blocked_units' => 2, 'price' => 1]);

        $handoverChat = $this->conversation();
        HandoverRequest::create(['conversation_id' => $handoverChat->id, 'reason' => 'complaint', 'summary' => 's', 'status' => 'open']);

        $response = $this->actingAs($this->viewer)->get(route('admin.analytics.index'))->assertOk();
        $report = $response->viewData('report');

        $this->assertSame(8000000.0, $report['revenue']);
        $this->assertSame(3, $report['bookings']['confirmed']);
        $this->assertSame(1, $report['bookings']['pending']);
        $this->assertSame(50.0, $report['occupancy']);
        $this->assertSame(3, $report['conversations']);
        $this->assertSame(1, $report['converted']);
        $this->assertSame(33.3, $report['conversion']);
        $this->assertSame(1, $report['handovers']);
        $this->assertSame('Big SUV', $report['top_vehicles']->first()->name);
        $this->assertSame(2, $report['top_vehicles']->first()->bookings);
    }

    public function test_ai_topics_come_from_the_tools_it_called(): void
    {
        $chat = $this->conversation();

        foreach ([['policies', 'refund'], ['policies', 'deposit'], ['delivery', 'airport']] as [$category, $query]) {
            $chat->messages()->create(['role' => ConversationMessage::ROLE_ASSISTANT, 'content' => 'ok', 'tool_calls' => [
                ['name' => 'search_knowledge', 'input' => ['category' => $category, 'query' => $query]],
                ['name' => 'check_availability', 'input' => []],
            ]]);
        }
        $chat->messages()->create(['role' => ConversationMessage::ROLE_CUSTOMER, 'content' => 'private words', 'tool_calls' => null]);

        $report = $this->actingAs($this->viewer)->get(route('admin.analytics.index'))->assertOk()->assertDontSee('private words')->viewData('report');

        $this->assertSame(['policies' => 2, 'delivery' => 1], $report['topics']->all());
        $this->assertSame(3, $report['tools']['check_availability']);
    }

    public function test_other_rentals_are_excluded_and_an_empty_period_renders(): void
    {
        $other = Rental::create(['name' => 'Other', 'slug' => 'other']);
        $van = $other->vehicles()->create(['name' => 'Van', 'slug' => 'van', 'base_price' => 1, 'seats' => 8, 'is_active' => true]);
        Booking::create(['reference' => 'BK-OTHER1', 'rental_id' => $other->id, 'vehicle_id' => $van->id, 'customer_name' => 'X', 'pickup_date' => now()->toDateString(), 'return_date' => now()->addDay()->toDateString(), 'total_price' => 5000000, 'status' => 'confirmed']);

        $report = $this->actingAs($this->viewer)->get(route('admin.analytics.index', ['days' => 7]))->assertOk()->viewData('report');

        $this->assertSame(0.0, $report['revenue']);
        $this->assertNull($report['occupancy']);
        $this->assertNull($report['conversion']);
        $this->assertTrue($report['top_vehicles']->isEmpty());
    }

    public function test_guests_are_sent_to_login_and_odd_periods_fall_back(): void
    {
        $this->get(route('admin.analytics.index'))->assertRedirect(route('admin.login'));

        $this->actingAs($this->viewer)->get(route('admin.analytics.index', ['days' => 5000]))->assertOk()->assertSee('Last 30 days');
    }
}
