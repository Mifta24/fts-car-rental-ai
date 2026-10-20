<?php

namespace Tests\Feature;

use App\Exceptions\PricingOptionRejected;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\DurationDiscount;
use App\Models\PromoCode;
use App\Models\Rental;
use App\Models\RentalAddon;
use App\Models\RentalUser;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use App\Services\Agent\RentalAgentTools;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PricingOptionsTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->vehicle = $this->rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1000000, 'seats' => 4, 'is_active' => true, 'driver_available' => true, 'driver_price' => 200000]);

        foreach (range(10, 20) as $offset) {
            VehicleInventory::create(['vehicle_id' => $this->vehicle->id, 'rental_date' => now()->addDays($offset)->toDateString(), 'total_units' => 3, 'booked_units' => 0, 'price' => 1000000]);
        }
    }

    private function quote(int $days = 2, int $units = 1, bool $driver = false, array $addons = [], ?string $promo = null): ?array
    {
        $from = CarbonImmutable::parse(now()->addDays(10)->toDateString());

        return app(ReservationService::class)->quote($this->vehicle, $from, $from->addDays($days), $units, $driver, addonIds: $addons, promoCode: $promo);
    }

    private function trip(array $extra = []): array
    {
        return [
            'vehicle_slug' => 'city-car', 'pickup_date' => now()->addDays(10)->toDateString(), 'return_date' => now()->addDays(12)->toDateString(),
            'passengers' => 2, 'units' => 1, ...$extra,
        ];
    }

    public function test_without_options_the_price_is_unchanged(): void
    {
        $quote = $this->quote(3, 2, driver: true);

        $this->assertEquals(2 * 3 * 1000000 + 2 * 3 * 200000, $quote['grand_total']);
        $this->assertSame([], $quote['addons']);
        $this->assertNull($quote['promo']);
    }

    public function test_extras_are_charged_per_day_and_car_or_once_per_booking(): void
    {
        $seat = RentalAddon::factory()->create(['rental_id' => $this->rental->id, 'name' => 'Child seat', 'price' => 50000]);
        $gps = RentalAddon::factory()->perDay()->create(['rental_id' => $this->rental->id, 'name' => 'GPS', 'price' => 30000]);

        $quote = $this->quote(3, 2, addons: [$seat->id, $gps->id]);

        $this->assertEquals(50000 + 30000 * 3 * 2, $quote['addons_total']);
        $this->assertEquals(2 * 3 * 1000000 + 230000, $quote['grand_total']);
    }

    public function test_an_inactive_or_foreign_extra_is_rejected(): void
    {
        $off = RentalAddon::factory()->create(['rental_id' => $this->rental->id, 'is_active' => false]);
        $other = RentalAddon::factory()->create(['rental_id' => Rental::create(['name' => 'B', 'slug' => 'b'])->id]);

        foreach ([$off->id, $other->id, 9999] as $id) {
            try {
                $this->quote(addons: [$id]);
                $this->fail('Expected the extra to be rejected.');
            } catch (PricingOptionRejected $e) {
                $this->assertSame('addon_unknown', $e->reason);
            }
        }
    }

    public function test_the_highest_matching_long_rental_discount_applies_to_the_car_price_only(): void
    {
        DurationDiscount::factory()->create(['rental_id' => $this->rental->id, 'min_days' => 3, 'percent' => 5]);
        DurationDiscount::factory()->create(['rental_id' => $this->rental->id, 'min_days' => 7, 'percent' => 10]);
        $seat = RentalAddon::factory()->create(['rental_id' => $this->rental->id, 'price' => 100000]);

        $this->assertEquals(0, $this->quote(2)['duration_discount']['amount']);
        $this->assertEquals(150000, $this->quote(3)['duration_discount']['amount']);

        $week = $this->quote(7, addons: [$seat->id]);
        $this->assertEquals(700000, $week['duration_discount']['amount']);
        $this->assertEquals(7000000 - 700000 + 100000, $week['grand_total']);
    }

    public function test_a_percent_promo_applies_after_the_long_rental_discount_and_a_fixed_one_is_capped(): void
    {
        DurationDiscount::factory()->create(['rental_id' => $this->rental->id, 'min_days' => 3, 'percent' => 10]);
        PromoCode::factory()->create(['rental_id' => $this->rental->id, 'code' => 'SAVE10', 'discount_value' => 10]);
        PromoCode::factory()->fixed(99000000)->create(['rental_id' => $this->rental->id, 'code' => 'HUGE']);

        $percent = $this->quote(3, promo: ' save10 ');
        $this->assertEquals(300000 + 270000, $percent['discount_total']);
        $this->assertSame('SAVE10', $percent['promo']['code']);
        $this->assertEquals(3000000 - 570000, $percent['grand_total']);

        $this->assertEquals(0, $this->quote(3, promo: 'HUGE')['grand_total']);
    }

    public function test_promo_codes_are_checked_for_validity_dates_minimum_days_and_usage(): void
    {
        PromoCode::factory()->create(['rental_id' => $this->rental->id, 'code' => 'OFF', 'is_active' => false]);
        PromoCode::factory()->create(['rental_id' => $this->rental->id, 'code' => 'OLD', 'valid_until' => now()->subDay()->toDateString()]);
        PromoCode::factory()->create(['rental_id' => $this->rental->id, 'code' => 'SOON', 'valid_from' => now()->addDay()->toDateString()]);
        PromoCode::factory()->create(['rental_id' => $this->rental->id, 'code' => 'LONG', 'min_days' => 5]);
        $once = PromoCode::factory()->create(['rental_id' => $this->rental->id, 'code' => 'ONCE', 'max_uses' => 1]);

        $reasons = [];
        foreach (['NOPE' => 2, 'OFF' => 2, 'OLD' => 2, 'SOON' => 2, 'LONG' => 2] as $code => $days) {
            try {
                $this->quote($days, promo: $code);
            } catch (PricingOptionRejected $e) {
                $reasons[$code] = $e->reason;
            }
        }

        $this->assertSame(['NOPE' => 'promo_invalid', 'OFF' => 'promo_invalid', 'OLD' => 'promo_expired', 'SOON' => 'promo_expired', 'LONG' => 'promo_min_days'], $reasons);
        $this->assertNotNull($this->quote(5, promo: 'LONG')['promo']);

        $this->assertNotNull($this->quote(promo: 'ONCE'));
        $booking = $this->book(['promo_code' => 'ONCE']);
        $this->assertSame(1, $once->usesCount());

        $this->expectException(PricingOptionRejected::class);
        $this->quote(promo: 'ONCE');
    }

    public function test_a_cancelled_booking_gives_its_promo_use_back(): void
    {
        $once = PromoCode::factory()->create(['rental_id' => $this->rental->id, 'code' => 'ONCE', 'max_uses' => 1]);
        $booking = $this->book(['promo_code' => 'ONCE']);

        $booking->update(['status' => Booking::STATUS_CANCELLED]);

        $this->assertSame(0, $once->usesCount());
        $this->assertNotNull($this->quote(promo: 'ONCE')['promo']);
    }

    private function book(array $options = []): Booking
    {
        $from = CarbonImmutable::parse(now()->addDays(10)->toDateString());

        return app(ReservationService::class)->createRequest($this->rental, $this->vehicle, [
            'pickup_date' => $from, 'return_date' => $from->addDays(2), 'passengers' => 2, 'customer_name' => 'Guest', 'customer_phone' => '+81 90 1234 5678', ...$options,
        ]);
    }

    public function test_a_booking_keeps_a_snapshot_of_its_extras_and_discounts(): void
    {
        $seat = RentalAddon::factory()->create(['rental_id' => $this->rental->id, 'name' => 'Child seat', 'price' => 50000]);
        PromoCode::factory()->create(['rental_id' => $this->rental->id, 'code' => 'SAVE10', 'discount_value' => 10]);

        $booking = $this->book(['addon_ids' => [$seat->id], 'promo_code' => 'save10']);
        $seat->update(['price' => 999999]);

        $booking->refresh();
        $this->assertSame('Child seat', $booking->addons[0]['name']);
        $this->assertEquals(50000, $booking->addons[0]['total']);
        $this->assertSame('SAVE10', $booking->promo_code);
        $this->assertEquals(200000, $booking->promo_discount);
        $this->assertEquals(2000000 - 200000 + 50000, $booking->total_price);
    }

    public function test_the_quote_endpoint_returns_the_breakdown_and_localises_extra_names(): void
    {
        $seat = RentalAddon::factory()->create(['rental_id' => $this->rental->id, 'name' => 'Kursi bayi', 'translations' => ['en' => ['name' => 'Child seat']], 'price' => 50000]);

        $response = $this->postJson(route('reservation.quote', 'demo'), $this->trip(['addons' => [$seat->id], 'locale' => 'en']))->assertOk();

        $response->assertJsonPath('addons.0.name', 'Child seat')->assertJsonPath('addons_total', 50000)->assertJsonPath('grand_total', 2050000);
    }

    public function test_a_bad_promo_code_is_a_field_error_in_the_customers_language_and_books_nothing(): void
    {
        $this->postJson(route('reservation.quote', 'demo'), $this->trip(['promo_code' => 'NOPE', 'locale' => 'id']))
            ->assertStatus(422)->assertJsonValidationErrors('promo_code')->assertJsonPath('errors.promo_code.0', 'Kode promo ini tidak valid.');

        $this->postJson(route('reservation.store', 'demo'), $this->trip(['promo_code' => 'NOPE', 'customer_name' => 'Guest', 'contact_type' => 'phone', 'contact_value' => '+81 90 1234 5678']))
            ->assertStatus(422)->assertJsonValidationErrors('promo_code');

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, (int) VehicleInventory::sum('booked_units'));
    }

    public function test_the_ai_tools_price_extras_and_report_a_rejected_code_without_booking(): void
    {
        $seat = RentalAddon::factory()->create(['rental_id' => $this->rental->id, 'name' => 'Child seat', 'price' => 50000]);
        $conversation = $this->rental->conversations()->create(['customer_token' => (string) Str::uuid(), 'status' => 'active', 'locale' => 'en']);
        $tools = new RentalAgentTools($this->rental, $conversation, 'en');
        $args = ['vehicle_slug' => 'city-car', 'pickup_date' => now()->addDays(10)->toDateString(), 'return_date' => now()->addDays(12)->toDateString()];

        $options = json_decode($tools->dispatch('get_pricing_options', [])['text'], true);
        $this->assertSame($seat->id, $options['extras'][0]['id']);

        $priced = json_decode($tools->dispatch('check_availability', [...$args, 'addon_ids' => [$seat->id]])['text'], true);
        $this->assertEquals(2050000, $priced['grand_total']);

        $rejected = json_decode($tools->dispatch('create_booking_request', [...$args, 'passengers' => 2, 'customer_name' => 'Guest', 'customer_phone' => '+81 90 1234 5678', 'promo_code' => 'MADEUP'])['text'], true);
        $this->assertFalse($rejected['applied']);
        $this->assertSame(0, Booking::count());
    }

    public function test_staff_manage_pricing_and_changes_are_audited_but_viewers_cannot(): void
    {
        $staff = User::factory()->create();
        $viewer = User::factory()->create();
        $this->rental->users()->attach($staff->id, ['role' => RentalUser::ROLE_STAFF, 'status' => 'active']);
        $this->rental->users()->attach($viewer->id, ['role' => RentalUser::ROLE_VIEWER, 'status' => 'active']);

        $this->actingAs($staff)->post(route('admin.addons.store'), ['name' => 'GPS', 'price' => 30000, 'charge' => 'per_day', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('admin.promo-codes.store'), ['code' => 'spring', 'discount_type' => 'percent', 'discount_value' => 15, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('admin.duration-discounts.store'), ['min_days' => 5, 'percent' => 8])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('admin.duration-discounts.store'), ['min_days' => 5, 'percent' => 9])->assertSessionHasNoErrors();

        $this->assertSame('SPRING', PromoCode::firstOrFail()->code);
        $this->assertSame(1, DurationDiscount::count());
        $this->assertEquals(9, DurationDiscount::firstOrFail()->percent);
        $this->assertSame(4, AuditLog::where('action', 'like', 'pricing.%')->count());

        $this->actingAs($staff)->post(route('admin.promo-codes.store'), ['code' => 'SPRING', 'discount_type' => 'percent', 'discount_value' => 5])->assertSessionHasErrors('code');
        $this->actingAs($staff)->post(route('admin.promo-codes.store'), ['code' => 'BIG', 'discount_type' => 'percent', 'discount_value' => 150])->assertSessionHasErrors('discount_value');

        $this->actingAs($viewer)->get(route('admin.pricing.index'))->assertOk()->assertSee('GPS');
        $this->actingAs($viewer)->post(route('admin.addons.store'), ['name' => 'X', 'price' => 1, 'charge' => 'per_day'])->assertForbidden();
    }

    public function test_pricing_rows_of_another_rental_cannot_be_touched(): void
    {
        $staff = User::factory()->create();
        $this->rental->users()->attach($staff->id, ['role' => RentalUser::ROLE_STAFF, 'status' => 'active']);
        $foreign = Rental::create(['name' => 'B', 'slug' => 'b']);

        $this->actingAs($staff)->delete(route('admin.addons.destroy', RentalAddon::factory()->create(['rental_id' => $foreign->id])))->assertNotFound();
        $this->actingAs($staff)->put(route('admin.promo-codes.update', PromoCode::factory()->create(['rental_id' => $foreign->id])), ['code' => 'X1', 'discount_type' => 'percent', 'discount_value' => 5])->assertNotFound();
        $this->actingAs($staff)->delete(route('admin.duration-discounts.destroy', DurationDiscount::factory()->create(['rental_id' => $foreign->id])))->assertNotFound();
    }

    public function test_the_reservation_page_offers_extras_and_a_promo_field_only_when_the_rental_has_them(): void
    {
        $this->get(route('rental.reservation', 'demo'))->assertOk()->assertDontSee('name="promo_code"', false)->assertDontSee('name="addons"', false);

        RentalAddon::factory()->create(['rental_id' => $this->rental->id, 'name' => 'Child seat']);
        PromoCode::factory()->create(['rental_id' => $this->rental->id]);

        $this->get(route('rental.reservation', 'demo'))->assertOk()->assertSee('name="promo_code"', false)->assertSee('name="addons"', false)->assertSee('Child seat');
    }
}
