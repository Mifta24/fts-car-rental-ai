<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingInspection;
use App\Models\Rental;
use App\Models\RentalUser;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingInspectionTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private Vehicle $vehicle;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->staff = $this->member(RentalUser::ROLE_STAFF);
        $this->vehicle = $this->rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1000000, 'seats' => 4, 'is_active' => true]);
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $this->rental->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function booking(string $status = Booking::STATUS_CONFIRMED, ?Rental $rental = null): Booking
    {
        $rental ??= $this->rental;
        $vehicle = $rental->is($this->rental) ? $this->vehicle : $rental->vehicles()->create(['name' => 'Van', 'slug' => 'van', 'base_price' => 1, 'seats' => 8, 'is_active' => true]);

        return Booking::create([
            'reference' => 'BK-'.strtoupper(fake()->unique()->lexify('??????')), 'rental_id' => $rental->id, 'vehicle_id' => $vehicle->id,
            'customer_name' => 'Guest', 'pickup_date' => now()->subDays(3)->toDateString(), 'return_date' => now()->subDay()->toDateString(),
            'total_price' => 1000000, 'status' => $status,
        ]);
    }

    private function signature(): string
    {
        $png = base64_encode(hex2bin('89504e470d0a1a0a0000000d4948445200000001000000010806000000'));

        return 'data:image/png;base64,'.$png;
    }

    private function form(array $extra = []): array
    {
        return ['odometer_km' => 12000, 'fuel_percent' => 100, 'signed_by' => 'Guest', 'signature' => $this->signature(), ...$extra];
    }

    private function submitCheck(Booking $booking, string $type, array $data, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->post(route('admin.bookings.inspection.store', [$booking, $type]), $data);
    }

    public function test_pickup_is_recorded_with_photos_signature_and_audit(): void
    {
        $booking = $this->booking();

        $this->submitCheck($booking, 'pickup', $this->form([
            'condition_notes' => 'Small scratch on rear bumper',
            'photos' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('rear.png')],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.bookings.inspection.show', [$booking, 'pickup']));

        $inspection = $booking->inspections()->firstOrFail();
        $this->assertSame(12000, $inspection->odometer_km);
        $this->assertSame($this->staff->id, $inspection->inspected_by);
        $this->assertCount(2, $inspection->photos);
        Storage::disk('local')->assertExists([...$inspection->photos, $inspection->signature_path]);
        $this->assertSame(1, AuditLog::where('action', 'inspection.pickup_completed')->count());
    }

    public function test_return_needs_a_pickup_first_and_cannot_roll_the_odometer_back(): void
    {
        $booking = $this->booking();

        $this->submitCheck($booking, 'return', $this->form())->assertSessionHas('error');
        $this->assertSame(0, BookingInspection::count());

        $this->submitCheck($booking, 'pickup', $this->form(['odometer_km' => 12000]));
        $booking = $booking->fresh();

        $this->submitCheck($booking, 'return', $this->form(['odometer_km' => 11000]))->assertSessionHasErrors('odometer_km');
        $this->submitCheck($booking, 'return', $this->form(['odometer_km' => 12400, 'fuel_percent' => 50, 'late_fee' => 150000, 'damage_fee' => 250000, 'fee_notes' => 'Late + bumper']))
            ->assertSessionHasNoErrors();

        $return = $booking->inspections()->where('type', 'return')->firstOrFail();
        $this->assertSame(400000.0, $return->extraCharges());
        $this->assertSame(1000000.0, (float) $booking->fresh()->total_price);
    }

    public function test_fees_are_only_accepted_on_the_return_check(): void
    {
        $this->submitCheck($this->booking(), 'pickup', $this->form(['damage_fee' => 5000]))->assertSessionHasErrors('damage_fee');
    }

    public function test_only_confirmed_bookings_can_be_handed_over_and_a_report_is_final(): void
    {
        $pending = $this->booking(Booking::STATUS_PENDING);
        $this->submitCheck($pending, 'pickup', $this->form())->assertSessionHas('error');

        $booking = $this->booking();
        $this->submitCheck($booking, 'pickup', $this->form())->assertSessionHasNoErrors();
        $this->submitCheck($booking, 'pickup', $this->form(['odometer_km' => 99999]))->assertSessionHas('error');

        $this->assertSame(1, $booking->inspections()->count());
        $this->assertSame(12000, $booking->inspections()->first()->odometer_km);
    }

    public function test_a_forged_signature_or_wrong_file_is_rejected(): void
    {
        $booking = $this->booking();

        $this->submitCheck($booking, 'pickup', $this->form(['signature' => 'data:image/png;base64,'.base64_encode('<?php echo 1;')]))->assertSessionHasErrors('signature');
        $this->submitCheck($booking, 'pickup', $this->form(['signature' => 'not-a-data-url']))->assertSessionHasErrors('signature');
        $this->submitCheck($booking, 'pickup', $this->form(['photos' => [UploadedFile::fake()->create('shell.php', 10, 'text/x-php')]]))->assertSessionHasErrors('photos.0');
        $this->submitCheck($booking, 'pickup', $this->form(['photos' => array_fill(0, 11, UploadedFile::fake()->image('a.jpg'))]))->assertSessionHasErrors('photos');

        $this->assertSame(0, BookingInspection::count());
    }

    public function test_viewers_can_read_but_not_write_and_files_are_staff_only(): void
    {
        $viewer = $this->member(RentalUser::ROLE_VIEWER);
        $booking = $this->booking();
        $this->submitCheck($booking, 'pickup', $this->form(['photos' => [UploadedFile::fake()->image('front.jpg')]]));
        $inspection = $booking->inspections()->firstOrFail();

        $this->submitCheck($this->booking(), 'pickup', $this->form(), $viewer)->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.bookings.inspection.show', [$booking, 'pickup']))->assertOk()->assertSee('12.000 km');
        $this->actingAs($viewer)->get(route('admin.inspections.photo', [$inspection, 0]))->assertOk();
        $this->actingAs($viewer)->get(route('admin.inspections.signature', $inspection))->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get(route('admin.inspections.signature', $inspection))->assertRedirect(route('admin.login'));

        $outsider = User::factory()->create();
        $other = Rental::create(['name' => 'Other', 'slug' => 'other']);
        $other->users()->attach($outsider->id, ['role' => 'owner', 'status' => 'active']);
        $this->actingAs($outsider)->get(route('admin.inspections.photo', [$inspection, 0]))->assertNotFound();
        $this->actingAs($outsider)->get(route('admin.bookings.inspection.show', [$booking, 'pickup']))->assertNotFound();
    }

    public function test_a_booking_of_another_rental_cannot_be_inspected(): void
    {
        $other = Rental::create(['name' => 'Other', 'slug' => 'other']);

        $this->submitCheck($this->booking(rental: $other), 'pickup', $this->form())->assertNotFound();
        $this->assertSame(0, BookingInspection::count());
    }

    public function test_the_booking_list_links_to_the_checks(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertSee('Pickup check')->assertSee('Return check');
        $this->submitCheck($booking, 'pickup', $this->form());
        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertSee('✓ Pickup check');
    }
}
