<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVehicleInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Vehicle $vehicle;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'timezone' => 'UTC']);
        $this->vehicle = $this->rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 500000, 'seats' => 4, 'is_active' => true]);
        $this->staff = User::factory()->create();
        $this->rental->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    public function test_staff_can_open_a_range_of_days(): void
    {
        $start = now('UTC')->addDay();

        $this->actingAs($this->staff)->put(route('admin.vehicles.inventory.update', $this->vehicle), [
            'start_date' => $start->toDateString(),
            'end_date' => $start->addDays(4)->toDateString(),
            'total_units' => 3,
            'price' => 750000,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(5, $this->vehicle->inventory()->count());
        $this->assertSame(3, $this->vehicle->inventory()->first()->total_units);
        $this->assertSame('750000.00', $this->vehicle->inventory()->first()->price);
    }

    public function test_units_already_booked_are_never_taken_away(): void
    {
        $date = now('UTC')->addDays(2)->toDateString();
        VehicleInventory::create(['vehicle_id' => $this->vehicle->id, 'rental_date' => $date, 'total_units' => 4, 'booked_units' => 3, 'price' => 1]);

        $this->actingAs($this->staff)->put(route('admin.vehicles.inventory.update', $this->vehicle), [
            'start_date' => $date, 'end_date' => $date, 'total_units' => 1, 'price' => 900000,
        ])->assertRedirect();

        $day = $this->vehicle->inventory()->firstOrFail();
        $this->assertSame(3, $day->total_units);
        $this->assertSame(3, $day->booked_units);
        $this->assertSame('900000.00', $day->price);
    }

    public function test_the_weekday_filter_limits_which_days_are_touched(): void
    {
        $monday = now('UTC')->next('Monday');

        $this->actingAs($this->staff)->put(route('admin.vehicles.inventory.update', $this->vehicle), [
            'start_date' => $monday->toDateString(),
            'end_date' => $monday->addDays(13)->toDateString(),
            'total_units' => 1,
            'price' => 100,
            'weekdays' => [6, 0],
        ])->assertRedirect();

        $this->assertSame(4, $this->vehicle->inventory()->count());
        $this->vehicle->inventory->each(fn ($day) => $this->assertContains($day->rental_date->dayOfWeek, [0, 6]));
    }

    public function test_past_dates_and_overlong_ranges_are_rejected(): void
    {
        $this->actingAs($this->staff)->put(route('admin.vehicles.inventory.update', $this->vehicle), [
            'start_date' => now('UTC')->subDay()->toDateString(), 'end_date' => now('UTC')->addDay()->toDateString(), 'total_units' => 1, 'price' => 1,
        ])->assertSessionHasErrors('start_date');

        $this->put(route('admin.vehicles.inventory.update', $this->vehicle), [
            'start_date' => now('UTC')->toDateString(), 'end_date' => now('UTC')->addDays(400)->toDateString(), 'total_units' => 1, 'price' => 1,
        ])->assertSessionHasErrors('end_date');

        $this->assertSame(0, $this->vehicle->inventory()->count());
    }

    public function test_the_calendar_page_lists_open_days_and_warns_when_none_are_open(): void
    {
        $this->actingAs($this->staff)->get(route('admin.vehicles.inventory', $this->vehicle))
            ->assertOk()
            ->assertSee('this car cannot be booked');

        VehicleInventory::create(['vehicle_id' => $this->vehicle->id, 'rental_date' => now('UTC')->addDays(30)->toDateString(), 'total_units' => 2, 'booked_units' => 1, 'price' => 123000]);

        $this->get(route('admin.vehicles.inventory', $this->vehicle))->assertOk()->assertSee('123.000')->assertDontSee('this car cannot be booked');
    }

    public function test_another_rentals_vehicle_cannot_be_managed(): void
    {
        $other = Rental::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->vehicles()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'seats' => 1, 'is_active' => true]);

        $this->actingAs($this->staff)->get(route('admin.vehicles.inventory', $foreign))->assertNotFound();
        $this->put(route('admin.vehicles.inventory.update', $foreign), ['start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'total_units' => 1, 'price' => 1])->assertNotFound();
    }
}
