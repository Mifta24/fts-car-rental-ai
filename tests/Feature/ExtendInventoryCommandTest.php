<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use App\Models\VehicleMaintenance;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtendInventoryCommandTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->vehicle = $rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1000000, 'seats' => 4, 'is_active' => true]);
        $this->today = CarbonImmutable::now($rental->timezone)->startOfDay();

        for ($day = 0; $day < 7; $day++) {
            VehicleInventory::create([
                'vehicle_id' => $this->vehicle->id,
                'rental_date' => $this->today->addDays($day),
                'total_units' => 3,
                'booked_units' => $day === 2 ? 2 : 0,
                'price' => $day === 4 ? 1150000 : 1000000,
            ]);
        }
    }

    private function day(int $offset): ?VehicleInventory
    {
        return VehicleInventory::query()->where('vehicle_id', $this->vehicle->id)->whereDate('rental_date', $this->today->addDays($offset)->toDateString())->first();
    }

    public function test_it_repeats_last_weeks_units_and_prices_without_touching_bookings(): void
    {
        $this->artisan('inventory:extend', ['--days' => 30])->assertSuccessful();

        $this->assertNotNull($this->day(30));
        $this->assertNull($this->day(31));
        $this->assertSame('1150000.00', $this->day(11)->price);
        $this->assertSame('1000000.00', $this->day(12)->price);
        $this->assertSame(3, $this->day(20)->total_units);
        $this->assertSame(0, $this->day(9)->booked_units);
        $this->assertSame(2, $this->day(2)->booked_units);
    }

    public function test_an_open_service_that_runs_past_the_old_horizon_blocks_the_new_days(): void
    {
        VehicleMaintenance::create(['vehicle_id' => $this->vehicle->id, 'type' => VehicleMaintenance::TYPE_SERVICE, 'title' => 'Overhaul', 'starts_on' => $this->today->addDays(5), 'ends_on' => $this->today->addDays(9)]);

        $this->artisan('inventory:extend', ['--days' => 30])->assertSuccessful();

        $this->assertSame(1, $this->day(5)->blocked_units);
        $this->assertSame(1, $this->day(9)->blocked_units);
        $this->assertSame(0, $this->day(10)->blocked_units);
    }

    public function test_running_it_twice_adds_nothing_the_second_time(): void
    {
        $this->artisan('inventory:extend', ['--days' => 30])->assertSuccessful();
        $count = VehicleInventory::count();

        $this->artisan('inventory:extend', ['--days' => 30])->expectsOutputToContain('Opened 0 day(s)')->assertSuccessful();
        $this->assertSame($count, VehicleInventory::count());
        $this->assertSame(31, $count);
    }

    public function test_inactive_vehicles_are_left_alone(): void
    {
        $this->vehicle->update(['is_active' => false]);

        $this->artisan('inventory:extend', ['--days' => 30])->assertSuccessful();

        $this->assertSame(7, VehicleInventory::count());
    }

    public function test_it_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'inventory:extend'));

        $this->assertCount(1, $events);
        $this->assertSame('0 1 * * *', $events->first()->expression);
    }
}
