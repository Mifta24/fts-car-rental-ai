<?php

namespace Tests\Feature;

use App\Models\Rental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RentalSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_screen_links_to_the_only_published_rental(): void
    {
        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        Rental::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('FTS Car Rental AI')
            ->assertSee('Enter Demo')
            ->assertSee('href="'.route('rental.vehicles', ['rentalSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('rental.services', ['rentalSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('rental.reservation', ['rentalSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('rental.staff', ['rentalSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertDontSee('Draft');
    }

    public function test_opening_screen_lists_every_published_rental_when_there_are_several(): void
    {
        Rental::create(['name' => 'First Rental', 'slug' => 'first', 'public_status' => 'published']);
        Rental::create(['name' => 'Second Rental', 'slug' => 'second', 'public_status' => 'published']);
        Rental::create(['name' => 'Hidden Rental', 'slug' => 'hidden']);

        $this->get('/')->assertOk()->assertSee('First Rental')->assertSee('Second Rental')->assertDontSee('Hidden Rental');
    }

    public function test_opening_screen_explains_when_no_rental_is_published(): void
    {
        $this->get('/?lang=en')->assertOk()->assertSee('The virtual showroom is being prepared');
    }

    public function test_chat_has_separate_open_and_close_controls_without_submitting_the_composer(): void
    {
        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-chat-launcher', false)
            ->assertSee('data-chat-composer', false)
            ->assertSee('type="button" data-chat-close', false)
            ->assertSee('aria-controls="agent-chat-log"', false);
    }

    public function test_chat_exposes_consistent_status_draft_and_loading_copy_for_each_locale(): void
    {
        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-label-status-sent="Message sent"', false)
            ->assertSee('data-label-status-waiting="Waiting for staff reply"', false)
            ->assertSee('data-label-status-replied="Staff has replied"', false)
            ->assertSee('data-label-draft-title="Unsaved message"', false)
            ->assertSee('data-label-draft-keep="Keep typing"', false)
            ->assertSee('data-label-draft-discard="Discard draft"', false)
            ->assertSee('data-thinking-indicator', false)
            ->assertSee('data-chat-status', false)
            ->assertSee('data-draft-dialog', false);

        $this->get('/demo?lang=ja')
            ->assertOk()
            ->assertSee('data-label-status-sent="メッセージを送信しました"', false)
            ->assertSee('data-label-status-waiting="スタッフの返信を待っています"', false)
            ->assertSee('data-label-status-replied="スタッフが返信しました"', false)
            ->assertSee('data-label-draft-title="未送信のメッセージ"', false)
            ->assertSee('data-label-draft-keep="入力を続ける"', false)
            ->assertSee('data-label-draft-discard="下書きを破棄"', false);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('data-label-status-sent="Pesan terkirim"', false)
            ->assertSee('data-label-status-waiting="Menunggu balasan staf"', false)
            ->assertSee('data-label-status-replied="Staf telah membalas"', false)
            ->assertSee('data-label-draft-title="Pesan belum dikirim"', false)
            ->assertSee('data-label-draft-keep="Lanjut mengetik"', false)
            ->assertSee('data-label-draft-discard="Buang draft"', false);
    }

    public function test_the_services_page_lists_only_active_services_of_the_current_rental(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Airport delivery', 'body' => 'Free for rentals of 3 days or more.', 'is_active' => true, 'image_url' => 'https://example.test/delivery.jpg']);
        $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Hidden perk', 'body' => 'Private.', 'is_active' => false]);
        $other = Rental::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->knowledgeItems()->create(['category' => 'services', 'title' => 'Other perk', 'body' => 'Other rental.', 'is_active' => true]);

        $this->get('/demo/services')
            ->assertOk()
            ->assertSee('Airport delivery')
            ->assertDontSee('Hidden perk')
            ->assertDontSee('Other perk')
            ->assertSee('class="service-card"', false)
            ->assertSee('https://example.test/delivery.jpg', false)
            ->assertSee('class="site-header"', false)
            ->assertSee('class="page-hero"', false);
        $this->get('/demo?lang=en')->assertOk()->assertSee('The right car for your trip,')->assertSee('ready when you are.');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('旅にぴったりの一台を、');
    }

    public function test_unpublished_showroom_is_not_accessible(): void
    {
        Rental::create(['name' => 'Draft', 'slug' => 'draft']);
        $this->get('/draft')->assertNotFound();
    }

    public function test_the_vehicles_page_lists_only_active_vehicles_of_the_current_rental(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $first = $rental->vehicles()->create([
            'name' => 'City Car', 'slug' => 'city-car', 'base_price' => 950000, 'seats' => 3,
            'transmission' => 'automatic', 'fuel_type' => 'petrol', 'body_type' => 'suv', 'features' => ['bluetooth', 'sunroof'],
            'insurance_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'Side view', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Rear view', 'sort_order' => 1]);
        $rental->vehicles()->create(['name' => 'Family Van', 'slug' => 'family-van', 'base_price' => 1800000, 'seats' => 5, 'body_type' => 'mpv', 'is_active' => true, 'sort_order' => 1]);
        $rental->vehicles()->create(['name' => 'Retired Vehicle', 'slug' => 'retired-vehicle', 'base_price' => 1, 'seats' => 1, 'is_active' => false]);
        $other = Rental::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->vehicles()->create(['name' => 'Foreign Vehicle', 'slug' => 'foreign-vehicle', 'base_price' => 1, 'seats' => 1, 'is_active' => true]);

        $this->get('/demo/vehicles?lang=en')
            ->assertOk()
            ->assertSee('City Car')
            ->assertSee('Family Van')
            // the fleet browser: one card per active car, filterable by body type
            ->assertSee('class="section section-flush fleet-panel"', false)
            ->assertSee('data-fleet-card', false)
            ->assertSee('data-fleet-filter="suv"', false)
            ->assertSee('2 cars')
            ->assertSee('href="'.route('rental.vehicle', ['rentalSlug' => 'demo', 'vehicleSlug' => 'city-car', 'lang' => 'en']).'"', false)
            ->assertDontSee('Retired Vehicle')
            ->assertDontSee('Foreign Vehicle');
    }

    public function test_a_vehicle_page_shows_its_details_gallery_and_neighbouring_vehicles(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $first = $rental->vehicles()->create([
            'name' => 'City Car', 'slug' => 'city-car', 'base_price' => 950000, 'model_year' => 2023, 'seats' => 3,
            'transmission' => 'automatic', 'fuel_type' => 'petrol', 'body_type' => 'suv', 'features' => ['bluetooth', 'sunroof'],
            'insurance_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'Side view', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Rear view', 'sort_order' => 1]);
        $rental->vehicles()->create(['name' => 'Family Van', 'slug' => 'family-van', 'base_price' => 1800000, 'seats' => 5, 'is_active' => true, 'sort_order' => 1]);

        $vanUrl = route('rental.vehicle', ['rentalSlug' => 'demo', 'vehicleSlug' => 'family-van', 'lang' => 'en']);

        $this->get('/demo/vehicles/city-car?lang=en')
            ->assertOk()
            ->assertSee('Car 1 / 2')
            ->assertSee('Side view')
            ->assertSee('Automatic')
            ->assertSee('SUV')
            ->assertSee('Petrol')
            ->assertSee('https://example.test/two.jpg', false)
            ->assertSee('Final availability and rates are confirmed by our staff.')
            // both neighbours wrap around to the only other vehicle
            ->assertSee('rel="prev"', false)
            ->assertSee($vanUrl, false);

        $this->get('/demo/vehicles/retired-or-unknown')->assertNotFound();
        $this->get('/demo/vehicles/city-car?lang=id')->assertOk()->assertSee('Otomatis')->assertSee('Bensin');
    }

    public function test_vehicles_pages_follow_the_rental_publication_and_vehicle_state(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $rental->vehicles()->create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'seats' => 1, 'is_active' => false]);
        Rental::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/demo/vehicles?lang=en')->assertOk()->assertSee('Our fleet information will be available soon.');
        $this->get('/demo/vehicles/hidden')->assertNotFound();
        $this->get('/draft/vehicles')->assertNotFound();
    }

    public function test_the_home_page_links_to_the_fleet_and_the_other_pages(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 950000, 'seats' => 2, 'is_active' => true]);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('rental.vehicles', ['rentalSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('href="'.route('rental.services', ['rentalSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('href="'.route('rental.info', ['rentalSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('href="'.route('rental.staff', ['rentalSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('class="search-card"', false)
            ->assertSee('name="pickup_date"', false)
            ->assertSee('City Car');

        $this->get('/demo/vehicles?lang=id')
            ->assertOk()
            ->assertSee('data-scene="vehicles"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_reservation_wizard_and_staff_channels_render_from_rental_data(): void
    {
        $rental = Rental::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'whatsapp' => '+62 812-0000-1111',
            'phone' => '+62 361 000', 'email' => 'front@demo.test',
        ]);
        $rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 950000, 'seats' => 3, 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('rental.reservation', ['rentalSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('rental.staff', ['rentalSlug' => 'demo', 'lang' => 'en']).'"', false);

        $this->get('/demo/reservation?lang=en')
            ->assertOk()
            ->assertSee('data-scene="reservation"', false)
            ->assertSee('data-step="5"', false)
            ->assertSee('value="city-car"', false)
            ->assertSee('Step :current of :total', false)
            ->assertSee(route('reservation.store', 'demo'), false);

        $this->get('/demo/reservation?lang=id')->assertOk()->assertSee('Langkah :current dari :total', false);

        $this->get('/demo/reservation?lang=en&vehicle=city-car')
            ->assertOk()
            ->assertSee('data-preselect-vehicle="city-car"', false);

        $this->get('/demo/vehicles/city-car?lang=en')
            ->assertOk()
            ->assertSee('href="'.e(route('rental.reservation', ['rentalSlug' => 'demo', 'lang' => 'en', 'vehicle' => 'city-car'])).'"', false);

        $this->get('/demo/staff?lang=en')
            ->assertOk()
            ->assertSee('data-scene="staff"', false)
            ->assertSee('https://wa.me/6281200001111?text=', false)
            ->assertSee('tel:+62361000', false)
            ->assertSee('mailto:front@demo.test', false);

        $this->get('/draft/staff')->assertNotFound();
        $this->get('/draft/reservation')->assertNotFound();
    }

    public function test_the_contact_page_offers_the_agent_and_every_staff_channel(): void
    {
        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'phone' => '+62 361 000']);

        $this->get('/demo/staff?lang=en')
            ->assertOk()
            ->assertSee('class="contact-cards"', false)
            ->assertSee('contact-card contact-card-agent', false)
            ->assertSee('tel:+62361000', false);
    }

    public function test_the_information_page_shows_the_counter_hours_card(): void
    {
        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('data-scene="info"', false)
            ->assertSee('class="info-hours"', false);
    }

    public function test_every_page_carries_the_same_chat_widget_controls(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 950000, 'seats' => 2, 'is_active' => true]);
        $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Child seat', 'body' => 'Available on request.', 'is_active' => true]);

        foreach (['/demo', '/demo/info', '/demo/vehicles', '/demo/services', '/demo/reservation', '/demo/staff'] as $path) {
            $this->get($path.'?lang=en')
                ->assertOk()
                ->assertSee('data-chat-launcher', false)
                ->assertSee('data-chat-composer', false)
                ->assertSee('aria-controls="agent-chat-log"', false);
        }
    }

    public function test_each_service_has_its_own_page_with_neighbours_and_an_index(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $pool = $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Airport delivery', 'body' => 'Open 07:00 to 20:00.', 'is_active' => true, 'sort_order' => 0]);
        $gym = $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Roadside support', 'body' => 'Available 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $hidden = $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Hidden perk', 'body' => 'Private.', 'is_active' => false]);

        $url = fn ($item) => route('rental.service', ['rentalSlug' => 'demo', 'serviceId' => $item->id, 'lang' => 'en']);

        $this->get('/demo/services?lang=en')
            ->assertOk()
            ->assertSee('data-scene="services"', false)
            ->assertSee($url($pool), false)
            ->assertSee($url($gym), false)
            ->assertDontSee($url($hidden), false);

        $this->get($url($pool))
            ->assertOk()
            ->assertSee('Service 1 / 2')
            ->assertSee('Open 07:00 to 20:00.')
            ->assertSee('Ask about this service')
            // both neighbours wrap around to the only other service
            ->assertSee('rel="prev"', false)
            ->assertSee($url($gym), false);

        $this->get($url($hidden))->assertNotFound();
        $this->get('/demo/services/999999')->assertNotFound();
        $this->get('/draft/services')->assertNotFound();
        $this->get('/draft/info')->assertNotFound();
    }

    public function test_the_rental_information_scene_lists_only_approved_about_policy_and_faq_entries(): void
    {
        $rental = Rental::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'address' => 'Jl. Bypass 8', 'city' => 'Bali', 'country' => 'Indonesia',
            'open_time' => '07:00', 'close_time' => '21:00', 'description' => 'A friendly car rental in Bali.', 'latitude' => -8.8, 'longitude' => 115.23,
        ]);
        $rental->knowledgeItems()->create(['category' => 'policies', 'title' => 'Cancellation', 'body' => 'Free until 48 hours before pick-up.', 'is_active' => true]);
        $rental->knowledgeItems()->create(['category' => 'faq', 'title' => 'Is delivery free?', 'body' => 'Yes, for customers.', 'is_active' => true]);
        $rental->knowledgeItems()->create(['category' => 'policies', 'title' => 'Draft policy', 'body' => 'Not approved.', 'is_active' => false]);
        $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Child seat', 'body' => 'Belongs to services.', 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('rental.info', ['rentalSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertDontSee('data-showroom-panel="info"', false)
            ->assertDontSee('Free until 48 hours before pick-up.');

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('data-scene="info"', false)
            ->assertSee('Free until 48 hours before pick-up.')
            ->assertSee('Is delivery free?')
            ->assertSee('class="info-hours-time"', false)
            ->assertSeeInOrder(['07:00', '21:00'])
            ->assertSee('https://www.google.com/maps?q=-8.8000000,115.2300000', false)
            ->assertDontSee('Not approved.');
    }

    public function test_the_home_page_and_the_opening_screen_are_localised(): void
    {
        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=id')->assertOk()->assertSee('Mobil yang tepat untuk perjalanan Anda,')->assertSee('Cari mobil Anda');
        $this->get('/?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('Welcome to');
    }

    public function test_the_agent_introduces_vehicles_using_only_stored_rental_data(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $rental->vehicles()->create([
            'name' => 'City Car', 'slug' => 'city-car', 'description' => 'A calm hatchback for city trips', 'base_price' => 950000, 'model_year' => 2023, 'luggage_capacity' => 2,
            'seats' => 3, 'transmission' => 'automatic', 'fuel_type' => 'petrol', 'body_type' => 'suv', 'insurance_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $rental->vehicles()->create(['name' => 'Family Van', 'slug' => 'family-van', 'base_price' => 2200000, 'seats' => 4, 'insurance_included' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->get('/demo/vehicles?lang=en')
            ->assertOk()
            ->assertSee('data-narrator', false)
            ->assertSee('We have 2 cars, from IDR 950.000 per day.');

        $this->get('/demo/vehicles/city-car?lang=en')
            ->assertOk()
            ->assertSee('City Car. A calm hatchback for city trips. It seats 3 passengers and takes 2 bags. Automatic, Petrol. Basic insurance is included. Rates start from IDR 950.000 per day.');

        $this->get('/demo/vehicles/family-van?lang=en')
            ->assertOk()
            ->assertSee('Family Van. It seats 4 passengers and takes 2 bags. Automatic, Petrol. Rates start from IDR 2.200.000 per day. Final availability and rates are confirmed by our team.');

        $this->get('/demo/vehicles?lang=id')->assertOk()->assertSee('Ada 2 mobil, mulai dari IDR 950.000 per hari.');
    }

    public function test_no_vehicles_narration_is_shown_when_the_rental_has_no_vehicles(): void
    {
        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/vehicles?lang=en')->assertOk()->assertDontSee('data-key="vehicles"', false)->assertDontSee('Here is our fleet');
    }

    public function test_the_agent_introduces_services_and_rental_information_from_stored_data(): void
    {
        $rental = Rental::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'city' => 'Bali', 'country' => 'Indonesia',
            'open_time' => '07:00', 'close_time' => '21:00',
        ]);
        $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Airport delivery', 'body' => "Open 07:00 to 20:00.\nFlight number required.", 'is_active' => true, 'sort_order' => 0]);
        $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Roadside support', 'body' => 'Available 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $rental->knowledgeItems()->create(['category' => 'services', 'title' => 'Hidden perk', 'body' => 'Private.', 'is_active' => false, 'sort_order' => 2]);

        $pool = $rental->knowledgeItems()->where('title', 'Airport delivery')->firstOrFail();

        $this->get('/demo/services?lang=en')
            ->assertOk()
            ->assertSee('We have 2 services and packages, including Airport delivery; Roadside support.')
            ->assertDontSee('including Airport delivery; Roadside support; Hidden perk');

        $this->get(route('rental.service', ['rentalSlug' => 'demo', 'serviceId' => $pool->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-key="service-', false)
            ->assertSee('data-text="Open 07:00 to 20:00.', false);

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Demo. We are located in Bali, Indonesia. Our counter is open from 07:00 to 21:00.');

        $this->get('/demo/info?lang=id')->assertOk()->assertSee('Selamat datang di Demo. Kami berada di Bali, Indonesia.');
    }

    public function test_rental_information_narration_skips_missing_data(): void
    {
        Rental::create(['name' => 'Bare', 'slug' => 'bare', 'public_status' => 'published']);

        $this->get('/bare/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Bare. Our counter is open from 08:00 to 20:00. Below you will find the address')
            ->assertDontSee('We are located in');
    }

    public function test_the_sound_toggle_is_available_on_the_opening_screen_and_the_showroom_in_every_language(): void
    {
        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('Sound on')->assertSee('Sound off');
        $this->get('/?lang=id')->assertOk()->assertSee('Suara aktif')->assertSee('Suara mati');
        $this->get('/demo?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('data-label-off="Sound off"', false);
        $this->get('/demo/info?lang=ja')
            ->assertOk()
            ->assertSee('サウンドオン')
            ->assertSee('data-lang="ja-JP"', false)
            ->assertSee('data-voice-preference="female"', false);
    }

    public function test_a_vehicle_page_offers_booking_and_other_cars(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 950000, 'seats' => 2, 'is_active' => true, 'sort_order' => 0]);
        $rental->vehicles()->create(['name' => 'Family Van', 'slug' => 'family-van', 'base_price' => 2200000, 'seats' => 4, 'is_active' => true, 'sort_order' => 1]);
        $rental->vehicles()->create(['name' => 'Retired Vehicle', 'slug' => 'retired', 'base_price' => 1, 'seats' => 1, 'is_active' => false]);

        $this->get('/demo/vehicles/family-van?lang=id')
            ->assertOk()
            ->assertSee('class="booking-card"', false)
            ->assertSee('class="price-plate"', false)
            ->assertSee('IDR 2.200.000')
            ->assertSee('Mobil lain yang mungkin cocok')
            ->assertSee('City Car')
            ->assertDontSee('Retired Vehicle')
            ->assertSee('href="'.e(route('rental.reservation', ['rentalSlug' => 'demo', 'lang' => 'id', 'vehicle' => 'family-van'])).'"', false);

        // the header marks the fleet as the current section on its detail pages
        $navigation = Str::before(Str::after($this->get('/demo/vehicles/family-van?lang=id')->getContent(), '<nav class="site-nav"'), '</nav>');
        $this->assertSame(1, substr_count($navigation, 'aria-current="page"'));
    }

    public function test_each_page_shows_the_agent_mascot_in_its_own_pose(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $rental->vehicles()->create(['name' => 'City Car', 'slug' => 'city-car', 'base_price' => 950000, 'seats' => 2, 'is_active' => true]);

        $this->get('/demo')
            ->assertOk()
            ->assertSee('images/hero.webp', false)
            ->assertSee('class="hero-mascot"', false)
            ->assertSee('images/character/agent-presenting.svg', false);
        $this->get('/demo/reservation')->assertOk()->assertSee('images/character/agent-tablet.svg', false);
        $this->get('/demo/staff')->assertOk()->assertSee('images/character/agent-greeting.svg', false);

        // the avatars crop their face out of the greeting pose
        $this->get('/demo/info')->assertOk()->assertSee('--agent-image: url(\'', false)->assertSee('agent-greeting.svg', false);
    }

    public function test_service_icons_follow_what_the_entry_is_about(): void
    {
        $rental = Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $icon = fn (string $title, array $tags = []) => $rental->knowledgeItems()->make(['category' => 'services', 'title' => $title, 'body' => '-', 'tags' => $tags])->iconName();

        $this->assertSame('driver', $icon('Sewa dengan sopir'));
        $this->assertSame('delivery', $icon('Antar-jemput bandara'));
        $this->assertSame('insurance', $icon('Asuransi dan perlindungan'));
        $this->assertSame('gps', $icon('Navigasi GPS'));
        $this->assertSame('child', $icon('Kursi bayi'));
        $this->assertSame('package', $icon('Paket wisata Bali'));
        $this->assertSame('place', $icon('Rute favorit'));
        $this->assertSame('support', $icon('Bantuan darurat'));
        $this->assertSame('gps', $icon('Perlengkapan', ['gps']));
        $this->assertSame('star', $icon('Free coffee'));
    }

    public function test_every_nav_item_and_chat_chip_leads_to_its_page_with_a_topic_for_the_agent(): void
    {
        Rental::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $scenes = [
            'rental.vehicles' => 'Saya ingin melihat pilihan mobil yang tersedia.',
            'rental.services' => 'Apa saja layanan dan paket yang tersedia?',
            'rental.info' => 'Apa syarat sewa, deposit, dan kebijakan pembatalan?',
            'rental.reservation' => 'Saya ingin menyewa mobil. Bantu saya cek ketersediaan.',
            'rental.staff' => 'Saya ingin bicara dengan tim rental.',
        ];

        $response = $this->get('/demo?lang=id')->assertOk();

        foreach ($scenes as $route => $topic) {
            $url = route($route, ['rentalSlug' => 'demo', 'lang' => 'id']);

            $response->assertSee('href="'.$url.'"', false)
                ->assertSee('data-topic="'.$topic.'"', false);
            $this->get($url)->assertOk();
        }
    }
}
