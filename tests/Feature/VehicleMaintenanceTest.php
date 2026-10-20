<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Rental;
use App\Models\RentalUser;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use App\Models\VehicleMaintenance;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Vehicle $vehicle;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->staff = User::factory()->create();
        $this->rental->users()->attach($this->staff->id, ['role' => RentalUser::ROLE_STAFF, 'status' => 'active']);
        $this->vehicle = $this->rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1000000, 'seats' => 4, 'is_active' => true]);

        foreach (range(1, 10) as $offset) {
            VehicleInventory::create(['vehicle_id' => $this->vehicle->id, 'rental_date' => now()->addDays($offset)->toDateString(), 'total_units' => 1, 'booked_units' => 0, 'price' => 1000000]);
        }
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->toDateString();
    }

    private function blockedOn(int $offset): int
    {
        return (int) VehicleInventory::where('vehicle_id', $this->vehicle->id)->whereDate('rental_date', $this->day($offset))->value('blocked_units');
    }

    private function quote(int $from, int $to): ?array
    {
        return app(ReservationService::class)->quote($this->vehicle, CarbonImmutable::parse($this->day($from)), CarbonImmutable::parse($this->day($to)));
    }

    private function scheduleService(int $from, int $to): void
    {
        $this->actingAs($this->staff)->post(route('admin.vehicles.maintenance.store', $this->vehicle), [
            'type' => 'service', 'title' => 'Oil change', 'starts_on' => $this->day($from), 'ends_on' => $this->day($to),
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_a_service_blocks_the_car_for_its_dates_only(): void
    {
        $this->scheduleService(3, 4);

        $this->assertSame([0, 0, 1, 1, 0], array_map(fn ($d) => $this->blockedOn($d), [1, 2, 3, 4, 5]));
        $this->assertNull($this->quote(3, 5));
        $this->assertNotNull($this->quote(1, 3));
        $this->assertNotNull($this->quote(5, 7));
    }

    public function test_marking_it_done_or_deleting_it_gives_the_car_back(): void
    {
        $this->scheduleService(3, 4);
        $record = VehicleMaintenance::firstOrFail();

        $this->actingAs($this->staff)->post(route('admin.vehicles.maintenance.complete', [$this->vehicle, $record]))->assertRedirect();
        $this->assertSame(0, $this->blockedOn(3));
        $this->assertNotNull($this->quote(3, 5));

        $this->scheduleService(6, 6);
        $this->actingAs($this->staff)->delete(route('admin.vehicles.maintenance.destroy', [$this->vehicle, VehicleMaintenance::latest('id')->firstOrFail()]))->assertRedirect();
        $this->assertSame(0, $this->blockedOn(6));
    }

    public function test_editing_the_dates_moves_the_block(): void
    {
        $this->scheduleService(3, 4);
        $record = VehicleMaintenance::firstOrFail();

        $this->actingAs($this->staff)->put(route('admin.vehicles.maintenance.update', [$this->vehicle, $record]), [
            'type' => 'service', 'starts_on' => $this->day(5), 'ends_on' => $this->day(6),
        ])->assertSessionHasNoErrors();

        $this->assertSame([0, 0, 1, 1], array_map(fn ($d) => $this->blockedOn($d), [3, 4, 5, 6]));
    }

    public function test_days_opened_later_inherit_the_block(): void
    {
        $this->scheduleService(20, 21);

        $this->actingAs($this->staff)->put(route('admin.vehicles.inventory.update', $this->vehicle), [
            'start_date' => $this->day(19), 'end_date' => $this->day(22), 'total_units' => 1, 'price' => 1000000,
        ])->assertSessionHasNoErrors();

        $this->assertSame([0, 1, 1, 0], array_map(fn ($d) => $this->blockedOn($d), [19, 20, 21, 22]));
    }

    public function test_a_service_cannot_cover_days_that_customers_already_booked(): void
    {
        VehicleInventory::whereDate('rental_date', $this->day(4))->update(['booked_units' => 1]);

        $this->actingAs($this->staff)->post(route('admin.vehicles.maintenance.store', $this->vehicle), [
            'type' => 'service', 'starts_on' => $this->day(3), 'ends_on' => $this->day(5),
        ])->assertSessionHasErrors('starts_on');

        $this->assertSame(0, VehicleMaintenance::count());
    }

    public function test_a_spare_unit_can_still_go_to_the_workshop_on_a_booked_day(): void
    {
        VehicleInventory::whereDate('rental_date', $this->day(4))->update(['total_units' => 2, 'booked_units' => 1]);

        $this->scheduleService(4, 4);

        $this->assertSame(1, $this->blockedOn(4));
    }

    public function test_service_needs_dates_and_documents_need_an_expiry(): void
    {
        $this->actingAs($this->staff)->post(route('admin.vehicles.maintenance.store', $this->vehicle), ['type' => 'service'])->assertSessionHasErrors(['starts_on', 'ends_on']);
        $this->actingAs($this->staff)->post(route('admin.vehicles.maintenance.store', $this->vehicle), ['type' => 'insurance'])->assertSessionHasErrors('expires_on');
        $this->actingAs($this->staff)->post(route('admin.vehicles.maintenance.store', $this->vehicle), ['type' => 'service', 'starts_on' => $this->day(5), 'ends_on' => $this->day(3)])->assertSessionHasErrors('ends_on');
    }

    public function test_expiring_documents_warn_on_the_dashboard_until_renewed(): void
    {
        VehicleMaintenance::factory()->expiring('insurance', $this->day(10))->create(['vehicle_id' => $this->vehicle->id]);
        VehicleMaintenance::factory()->expiring('tax', $this->day(200))->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertOk()->assertSee('Insurance')->assertDontSee('Tax expires');

        VehicleMaintenance::factory()->expiring('insurance', $this->day(300))->create(['vehicle_id' => $this->vehicle->id]);

        $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Vehicle documents need attention');
    }

    public function test_only_staff_and_owners_can_change_records_and_other_rentals_are_off_limits(): void
    {
        $viewer = User::factory()->create();
        $this->rental->users()->attach($viewer->id, ['role' => RentalUser::ROLE_VIEWER, 'status' => 'active']);
        $payload = ['type' => 'service', 'starts_on' => $this->day(3), 'ends_on' => $this->day(4)];

        $this->actingAs($viewer)->get(route('admin.vehicles.maintenance.index', $this->vehicle))->assertOk();
        $this->actingAs($viewer)->post(route('admin.vehicles.maintenance.store', $this->vehicle), $payload)->assertForbidden();

        $other = Rental::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published'])
            ->vehicles()->create(['name' => 'Van', 'slug' => 'van', 'base_price' => 1, 'seats' => 8, 'is_active' => true]);
        $this->actingAs($this->staff)->post(route('admin.vehicles.maintenance.store', $other), $payload)->assertNotFound();

        $foreign = VehicleMaintenance::factory()->create(['vehicle_id' => $other->id]);
        $this->actingAs($this->staff)->delete(route('admin.vehicles.maintenance.destroy', [$this->vehicle, $foreign]))->assertNotFound();
    }

    public function test_maintenance_changes_are_audited(): void
    {
        $this->scheduleService(3, 4);

        $this->assertSame(1, AuditLog::where('action', 'maintenance.created')->count());
    }
}
