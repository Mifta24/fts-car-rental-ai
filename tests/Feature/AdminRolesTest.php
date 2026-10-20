<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Rental;
use App\Models\RentalUser;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Vehicle $vehicle;

    private function memberWithRole(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create();
        $this->rental->users()->attach($user->id, ['role' => $role, 'status' => $status]);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->vehicle = $this->rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1000000, 'seats' => 4, 'is_active' => true]);
    }

    private function booking(): Booking
    {
        VehicleInventory::create(['vehicle_id' => $this->vehicle->id, 'rental_date' => now()->addDays(10)->toDateString(), 'total_units' => 1, 'booked_units' => 0, 'price' => 1000000]);

        return Booking::create([
            'reference' => 'BK-ROLE01', 'rental_id' => $this->rental->id, 'vehicle_id' => $this->vehicle->id, 'customer_name' => 'Guest',
            'pickup_date' => now()->addDays(10)->toDateString(), 'return_date' => now()->addDays(10)->toDateString(),
            'total_price' => 1000000, 'status' => Booking::STATUS_PENDING,
        ]);
    }

    public function test_a_viewer_can_look_but_not_change_anything(): void
    {
        $viewer = $this->memberWithRole(RentalUser::ROLE_VIEWER);
        $booking = $this->booking();

        $this->actingAs($viewer)->get(route('admin.vehicles.index'))->assertOk()->assertDontSee('Add vehicle');
        $this->actingAs($viewer)->get(route('admin.bookings.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.vehicles.create'))->assertForbidden();
        $this->actingAs($viewer)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.vehicles.destroy', $this->vehicle))->assertForbidden();

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
        $this->assertNotNull($this->vehicle->fresh());
    }

    public function test_an_unknown_role_gets_viewer_access_only(): void
    {
        $odd = $this->memberWithRole('intern');

        $this->actingAs($odd)->get(route('admin.vehicles.index'))->assertOk();
        $this->actingAs($odd)->get(route('admin.vehicles.create'))->assertForbidden();
    }

    public function test_staff_can_work_but_not_manage_the_team_or_read_the_audit_log(): void
    {
        $staff = $this->memberWithRole(RentalUser::ROLE_STAFF);
        $booking = $this->booking();

        $this->actingAs($staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])->assertRedirect();
        $this->actingAs($staff)->get(route('admin.team.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.audit-log.index'))->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.team.update', $staff), ['role' => 'owner', 'status' => 'active'])->assertForbidden();
    }

    public function test_changes_are_written_to_the_audit_log(): void
    {
        $staff = $this->memberWithRole(RentalUser::ROLE_STAFF);
        $owner = $this->memberWithRole(RentalUser::ROLE_OWNER);
        $booking = $this->booking();

        $this->actingAs($staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED]);

        $log = AuditLog::where('action', 'booking.status_changed')->firstOrFail();
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame($this->rental->id, $log->rental_id);
        $this->assertSame('BK-ROLE01', $log->subject_label);
        $this->assertSame([Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], $log->changes['status']);

        $this->actingAs($owner)->get(route('admin.audit-log.index'))->assertOk()->assertSee('booking.status_changed')->assertSee($staff->name);
        $this->actingAs($owner)->get(route('admin.audit-log.index', ['area' => 'vehicle']))->assertOk()->assertDontSee('booking.status_changed');
    }

    public function test_a_price_change_is_recorded_with_old_and_new_values(): void
    {
        $staff = $this->memberWithRole(RentalUser::ROLE_STAFF);

        $this->actingAs($staff)->put(route('admin.vehicles.update', $this->vehicle), [
            'name' => 'City Car', 'seats' => 4, 'luggage_capacity' => 2, 'transmission' => 'automatic', 'fuel_type' => 'petrol',
            'base_price' => 1250000, 'is_active' => 1,
        ])->assertRedirect();

        $log = AuditLog::where('action', 'vehicle.updated')->firstOrFail();
        $this->assertEquals(1000000, $log->changes['base_price'][0]);
        $this->assertEquals(1250000, $log->changes['base_price'][1]);
    }

    public function test_the_owner_can_change_a_role_and_deactivate_a_member(): void
    {
        $owner = $this->memberWithRole(RentalUser::ROLE_OWNER);
        $staff = $this->memberWithRole(RentalUser::ROLE_STAFF);

        $this->actingAs($owner)->patch(route('admin.team.update', $staff), ['role' => 'viewer', 'status' => 'active'])->assertRedirect();
        $this->assertSame('viewer', $this->rental->users()->find($staff->id)->pivot->role);

        $this->actingAs($owner)->patch(route('admin.team.update', $staff), ['role' => 'viewer', 'status' => 'inactive'])->assertRedirect();
        $this->actingAs($staff)->get(route('admin.vehicles.index'))->assertForbidden();

        $this->assertSame(2, AuditLog::where('action', 'team.member_updated')->count());
    }

    public function test_the_last_active_owner_cannot_be_demoted(): void
    {
        $owner = $this->memberWithRole(RentalUser::ROLE_OWNER);

        $this->actingAs($owner)->patch(route('admin.team.update', $owner), ['role' => 'staff', 'status' => 'active'])->assertSessionHas('error');

        $this->assertSame('owner', $this->rental->users()->find($owner->id)->pivot->role);
    }

    public function test_an_owner_can_add_an_existing_user_but_not_one_from_another_rental_team_twice(): void
    {
        $owner = $this->memberWithRole(RentalUser::ROLE_OWNER);
        $newcomer = User::factory()->create();

        $this->actingAs($owner)->post(route('admin.team.store'), ['email' => $newcomer->email, 'role' => 'viewer'])->assertRedirect();
        $this->assertSame('viewer', $this->rental->users()->find($newcomer->id)->pivot->role);

        $this->actingAs($owner)->post(route('admin.team.store'), ['email' => $newcomer->email, 'role' => 'staff'])->assertSessionHasErrors('email');
        $this->actingAs($owner)->post(route('admin.team.store'), ['email' => 'nobody@example.test', 'role' => 'staff'])->assertSessionHasErrors('email');
    }

    public function test_team_actions_cannot_touch_members_of_other_rentals(): void
    {
        $owner = $this->memberWithRole(RentalUser::ROLE_OWNER);
        $stranger = User::factory()->create();

        $this->actingAs($owner)->patch(route('admin.team.update', $stranger), ['role' => 'owner', 'status' => 'active'])->assertNotFound();
    }
}
