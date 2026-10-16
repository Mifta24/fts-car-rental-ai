<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVehicleTest extends TestCase
{
    use RefreshDatabase;

    private Rental $rental;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->staff = User::factory()->create();
        $this->rental->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'City Car', 'seats' => 4, 'luggage_capacity' => 2, 'transmission' => 'automatic', 'fuel_type' => 'petrol',
            'base_price' => 950000, 'features' => 'Bluetooth, Child Seat', 'is_active' => 1, ...$overrides,
        ];
    }

    public function test_staff_can_add_a_car_with_a_unique_slug_and_normalised_features(): void
    {
        $this->actingAs($this->staff)->post(route('admin.vehicles.store'), $this->form())->assertRedirect(route('admin.vehicles.index'));
        $this->actingAs($this->staff)->post(route('admin.vehicles.store'), $this->form())->assertRedirect(route('admin.vehicles.index'));

        $this->assertSame(['city-car', 'city-car-1'], $this->rental->vehicles()->orderBy('id')->pluck('slug')->all());
        $this->assertSame(['bluetooth', 'child_seat'], $this->rental->vehicles()->first()->features);
    }

    public function test_a_car_needs_a_name_price_and_a_valid_transmission(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.vehicles.store'), $this->form(['name' => '', 'base_price' => '', 'transmission' => 'cvt']))
            ->assertSessionHasErrors(['name', 'base_price', 'transmission']);

        $this->assertSame(0, $this->rental->vehicles()->count());
    }

    public function test_only_real_web_addresses_are_accepted_as_car_photos(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.vehicles.store'), $this->form(['new_images' => [['url' => 'javascript:alert(1)']]]))
            ->assertSessionHasErrors('new_images.0.url');

        $this->actingAs($this->staff)
            ->post(route('admin.vehicles.store'), $this->form(['new_images' => [['url' => 'https://example.test/car.jpg', 'alt' => 'Front']]]))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://example.test/car.jpg', $this->rental->vehicles()->first()->images()->value('image_url'));
    }

    public function test_updating_a_car_keeps_its_slug_and_deleting_removes_it(): void
    {
        $vehicle = $this->rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 1, 'seats' => 4, 'is_active' => true]);

        $this->actingAs($this->staff)->put(route('admin.vehicles.update', $vehicle), $this->form(['name' => 'City Car Plus']))->assertRedirect(route('admin.vehicles.index'));
        $this->assertSame('city-car', $vehicle->fresh()->slug);
        $this->assertSame('City Car Plus', $vehicle->fresh()->name);

        $this->actingAs($this->staff)->delete(route('admin.vehicles.destroy', $vehicle))->assertRedirect(route('admin.vehicles.index'));
        $this->assertSoftDeleted($vehicle);
    }

    public function test_staff_cannot_edit_or_delete_another_rentals_car(): void
    {
        $other = Rental::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $vehicle = $other->vehicles()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'seats' => 4, 'is_active' => true]);

        $this->actingAs($this->staff)->get(route('admin.vehicles.edit', $vehicle))->assertNotFound();
        $this->actingAs($this->staff)->put(route('admin.vehicles.update', $vehicle), $this->form())->assertNotFound();
        $this->actingAs($this->staff)->delete(route('admin.vehicles.destroy', $vehicle))->assertNotFound();
        $this->assertModelExists($vehicle);
    }
}
