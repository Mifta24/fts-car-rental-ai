<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Rental $otherRental;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->otherRental = Rental::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);

        $this->staff = User::factory()->create();
        $this->rental->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function foreignBooking(): Booking
    {
        $vehicle = $this->otherRental->vehicles()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'seats' => 1, 'is_active' => true]);

        return Booking::create([
            'reference' => 'BK-FOREIGN', 'rental_id' => $this->otherRental->id, 'vehicle_id' => $vehicle->id, 'customer_name' => 'Foreign Customer',
            'pickup_date' => now()->addDays(3)->toDateString(), 'return_date' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);
    }

    private function foreignHandover(): HandoverRequest
    {
        $conversation = Conversation::create(['rental_id' => $this->otherRental->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);

        return HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Foreign complaint']);
    }

    public function test_the_login_page_is_open_to_visitors_and_closed_to_signed_in_staff(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('Car Rental Admin');

        $this->actingAs($this->staff)->get(route('admin.login'))->assertRedirect();
    }

    public function test_visitors_are_sent_to_the_login_page_for_every_admin_screen(): void
    {
        foreach (['admin.dashboard', 'admin.vehicles.index', 'admin.knowledge-items.index', 'admin.bookings.index', 'admin.handovers.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
    }

    public function test_staff_only_see_bookings_of_their_own_rental(): void
    {
        $this->foreignBooking();

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertDontSee('BK-FOREIGN')->assertDontSee('Foreign Customer');
    }

    public function test_staff_cannot_change_another_rentals_booking(): void
    {
        $booking = $this->foreignBooking();

        $this->actingAs($this->staff)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])
            ->assertNotFound();

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_staff_cannot_read_reply_to_or_resolve_another_rentals_handover(): void
    {
        $handover = $this->foreignHandover();

        $this->actingAs($this->staff)->get(route('admin.handovers.show', $handover))->assertNotFound();
        $this->actingAs($this->staff)->post(route('admin.handovers.reply', $handover), ['message' => 'Hello'])->assertNotFound();
        $this->actingAs($this->staff)->post(route('admin.handovers.resolve', $handover))->assertNotFound();

        $this->assertSame(0, ConversationMessage::count());
        $this->assertSame(HandoverRequest::STATUS_OPEN, $handover->fresh()->status);
    }

    public function test_staff_reply_reaches_the_customer_and_resolving_returns_the_conversation_to_the_ai(): void
    {
        $conversation = Conversation::create(['rental_id' => $this->rental->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en', 'status' => Conversation::STATUS_HANDED_OVER]);
        $handover = HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Noisy vehicle']);

        $this->actingAs($this->staff)->post(route('admin.handovers.reply', $handover), ['message' => 'We are on it.'])->assertRedirect();
        $this->assertSame('We are on it.', $conversation->messages()->where('role', ConversationMessage::ROLE_STAFF)->value('content'));

        $this->actingAs($this->staff)->post(route('admin.handovers.resolve', $handover))->assertRedirect(route('admin.handovers.index'));
        $this->assertSame(Conversation::STATUS_ACTIVE, $conversation->fresh()->status);
        $this->assertSame(HandoverRequest::STATUS_RESOLVED, $handover->fresh()->status);
    }

    public function test_admin_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('admin.login.store'), ['email' => $this->staff->email, 'password' => 'wrong'])->assertSessionHasErrors();
        }

        $this->post(route('admin.login.store'), ['email' => $this->staff->email, 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_staff_can_give_a_service_a_photo_and_only_a_real_url_is_accepted(): void
    {
        $item = $this->rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Child seat', 'body' => 'Free for rentals of 3 days or more.', 'is_active' => true]);
        $form = ['category' => 'services', 'title' => 'Child seat', 'body' => 'Free for rentals of 3 days or more.', 'is_active' => 1];

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('image_url');

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'ftp://example.test/seat.jpg'])
            ->assertSessionHasErrors('image_url');

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'https://example.test/seat.jpg'])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://example.test/seat.jpg', $item->fresh()->image_url);
    }
}
