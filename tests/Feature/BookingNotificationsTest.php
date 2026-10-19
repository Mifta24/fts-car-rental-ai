<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use App\Notifications\NewHandoverRequest;
use App\Services\Agent\RentalAgentTools;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Vehicle $vehicle;

    private User $staff;

    private User $inactiveStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'default_locale' => 'id']);
        $this->vehicle = $this->rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 100, 'seats' => 4, 'is_active' => true]);

        foreach ([1, 2] as $offset) {
            VehicleInventory::create(['vehicle_id' => $this->vehicle->id, 'rental_date' => now()->addDays($offset)->toDateString(), 'total_units' => 2, 'booked_units' => 0, 'price' => 100]);
        }

        $this->staff = User::factory()->create();
        $this->inactiveStaff = User::factory()->create();
        $this->rental->users()->attach($this->staff->id, ['role' => 'staff', 'status' => 'active']);
        $this->rental->users()->attach($this->inactiveStaff->id, ['role' => 'staff', 'status' => 'inactive']);
    }

    private function createRequest(?string $email = 'guest@example.com'): ?Booking
    {
        return app(ReservationService::class)->createRequest($this->rental, $this->vehicle, [
            'pickup_date' => now()->addDay()->toImmutable()->startOfDay(),
            'return_date' => now()->addDays(3)->toImmutable()->startOfDay(),
            'passengers' => 2,
            'customer_name' => 'Guest',
            'customer_email' => $email,
        ]);
    }

    public function test_a_new_request_notifies_active_staff_and_acknowledges_the_customer(): void
    {
        Notification::fake();

        $booking = $this->createRequest();

        Notification::assertSentTo($this->staff, NewBookingRequest::class);
        Notification::assertNotSentTo($this->inactiveStaff, NewBookingRequest::class);
        Notification::assertSentOnDemand(BookingStatusChanged::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'guest@example.com' && $notification->booking->is($booking));
    }

    public function test_a_customer_without_email_gets_no_mail(): void
    {
        Notification::fake();

        $this->createRequest(null);

        Notification::assertSentTo($this->staff, NewBookingRequest::class);
        Notification::assertSentOnDemandTimes(BookingStatusChanged::class, 0);
    }

    public function test_the_customer_is_emailed_in_the_conversation_language_when_staff_confirm(): void
    {
        $booking = $this->createRequest();
        Notification::fake();

        $this->actingAs($this->staff)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])
            ->assertRedirect();

        Notification::assertSentOnDemand(BookingStatusChanged::class, fn ($notification) => $notification->booking->status === Booking::STATUS_CONFIRMED && $notification->locale === 'id');
    }

    public function test_the_mail_for_each_status_renders(): void
    {
        $booking = $this->createRequest();

        foreach ([Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED] as $status) {
            $booking->status = $status;
            $mail = (new BookingStatusChanged($booking))->toMail(new \stdClass);

            $this->assertStringContainsString($booking->reference, $mail->subject);
        }
    }

    public function test_expired_requests_notify_the_customer(): void
    {
        $booking = $this->createRequest();
        $booking->forceFill(['created_at' => now()->subHours(ReservationService::PENDING_HOLD_HOURS + 1)])->save();
        Notification::fake();

        $this->artisan('bookings:expire-pending')->assertSuccessful();

        Notification::assertSentOnDemand(BookingStatusChanged::class, fn ($notification) => $notification->booking->status === Booking::STATUS_EXPIRED);
    }

    public function test_a_handover_notifies_active_staff(): void
    {
        Notification::fake();

        $conversation = Conversation::create(['rental_id' => $this->rental->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en', 'status' => Conversation::STATUS_ACTIVE]);

        (new RentalAgentTools($this->rental, $conversation, 'en'))->dispatch('request_human_handover', [
            'reason' => HandoverRequest::REASON_COMPLAINT,
            'summary' => 'Customer is upset about a late delivery.',
        ]);

        Notification::assertSentTo($this->staff, NewHandoverRequest::class);
        Notification::assertNotSentTo($this->inactiveStaff, NewHandoverRequest::class);
    }
}
