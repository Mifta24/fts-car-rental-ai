<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRentalSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Rental $alpha;

    private Rental $beta;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Rental::create(['name' => 'Alpha', 'slug' => 'alpha', 'public_status' => 'published']);
        $this->beta = Rental::create(['name' => 'Beta', 'slug' => 'beta', 'public_status' => 'published']);

        $this->user = User::factory()->create();
        $this->alpha->users()->attach($this->user->id, ['role' => 'owner', 'status' => 'active']);
        $this->beta->users()->attach($this->user->id, ['role' => 'staff', 'status' => 'active']);
    }

    public function test_user_defaults_to_first_rental_and_can_switch(): void
    {
        $this->actingAs($this->user)->get(route('admin.dashboard'))->assertOk()->assertSee('Alpha');

        $this->actingAs($this->user)
            ->post(route('admin.rental.switch'), ['rental_id' => $this->beta->id])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Beta');
    }

    public function test_user_cannot_switch_to_a_rental_they_do_not_belong_to(): void
    {
        $foreign = Rental::create(['name' => 'Foreign', 'slug' => 'foreign', 'public_status' => 'published']);

        $this->actingAs($this->user)
            ->post(route('admin.rental.switch'), ['rental_id' => $foreign->id])
            ->assertForbidden();
    }

    public function test_inactive_membership_cannot_be_switched_to(): void
    {
        $this->beta->users()->updateExistingPivot($this->user->id, ['status' => 'inactive']);

        $this->actingAs($this->user)
            ->post(route('admin.rental.switch'), ['rental_id' => $this->beta->id])
            ->assertForbidden();
    }

    public function test_login_error_follows_the_application_locale(): void
    {
        app()->setLocale('id');

        $this->post(route('admin.login.store'), ['email' => 'x@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
    }
}
