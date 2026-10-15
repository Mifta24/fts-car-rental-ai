@extends('rental.layout')

@php
    $vehicleName = $vehicle->translatedName($locale);
    $primaryImage = $vehicle->images->first();
    $vehicleUrl = fn ($other) => route('rental.vehicle', ['rentalSlug' => $rental->slug, 'vehicleSlug' => $other->slug, 'lang' => $locale]);
    $reserveUrl = route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale, 'vehicle' => $vehicle->slug]);
    $others = $vehicles->reject(fn ($other) => $other->id === $vehicle->id)->take(3);
@endphp

@section('title', $vehicleName.' · '.$rental->name)

@section('content')
    @include('rental.page-header', ['heading' => $vehicleName, 'lead' => $vehicle->translatedDescription($locale), 'crumbs' => [$site['nav_fleet'] => route('rental.vehicles', ['rentalSlug' => $rental->slug, 'lang' => $locale]), $vehicleName => null]])

    <section class="section section-flush">
        <div class="container detail-grid">
            <div class="detail-main">
                <div class="detail-pager">
                    <p class="eyebrow eyebrow-dark">{{ $showroom['vehicle_counter'] }} {{ $vehicleIndex + 1 }} / {{ $vehicles->count() }}</p>
                    @if ($vehicles->count() > 1)
                        <nav class="pager" aria-label="{{ $showroom['vehicle_scene'] }}">
                            <a href="{{ $vehicleUrl($previousVehicle) }}" class="btn btn-light btn-sm" rel="prev"><span aria-hidden="true">←</span> {{ $showroom['prev_vehicle'] }}</a>
                            <a href="{{ $vehicleUrl($nextVehicle) }}" class="btn btn-light btn-sm" rel="next">{{ $showroom['next_vehicle'] }} <span aria-hidden="true">→</span></a>
                        </nav>
                    @endif
                </div>

                <div data-vehicle-gallery class="gallery">
                    <div class="gallery-main">
                        @if ($primaryImage)
                            <img src="{{ $primaryImage->image_source }}" alt="{{ $primaryImage->alt_text }}" data-vehicle-gallery-main>
                        @else
                            <div class="gallery-empty">{{ $showroom['no_photo'] }}</div>
                        @endif
                    </div>
                    @if ($vehicle->images->count() > 1)
                        <ul class="gallery-thumbs" aria-label="{{ $showroom['gallery'] }}">
                            @foreach ($vehicle->images as $imageIndex => $image)
                                <li>
                                    <button type="button" class="gallery-thumb" data-vehicle-thumb data-src="{{ $image->image_source }}" data-alt="{{ $image->alt_text }}" aria-label="{{ $showroom['photo'] }} {{ $imageIndex + 1 }}" @if($imageIndex === 0) aria-current="true" @endif>
                                        <img src="{{ $image->image_source }}" alt="" loading="lazy">
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @include('rental.narrator', ['text' => $vehicleNarrations['vehicle'][$vehicle->slug], 'key' => 'vehicle-'.$vehicle->slug])

                <h2 class="detail-heading">{{ $showroom['spec_title'] }}</h2>
                <dl class="spec-table">
                    @if ($vehicle->body_type)
                        <div><dt><x-spec-icon name="body" width="18" height="18" />{{ $showroom['spec_body'] }}</dt><dd>{{ $vehicleTerms['body'][$vehicle->body_type] ?? Str::headline($vehicle->body_type) }}</dd></div>
                    @endif
                    @if ($vehicle->model_year)
                        <div><dt><x-spec-icon name="calendar" width="18" height="18" />{{ $showroom['spec_year'] }}</dt><dd>{{ $vehicle->model_year }}</dd></div>
                    @endif
                    <div><dt><x-spec-icon name="gearbox" width="18" height="18" />{{ $showroom['spec_transmission'] }}</dt><dd>{{ $vehicleTerms['transmission'][$vehicle->transmission] ?? Str::headline($vehicle->transmission) }}</dd></div>
                    <div><dt><x-spec-icon name="fuel" width="18" height="18" />{{ $showroom['spec_fuel'] }}</dt><dd>{{ $vehicleTerms['fuel'][$vehicle->fuel_type] ?? Str::headline($vehicle->fuel_type) }}</dd></div>
                    <div><dt><x-spec-icon name="seat" width="18" height="18" />{{ $showroom['spec_seats'] }}</dt><dd>{{ $vehicle->maxPassengers() }} {{ $labels['max_passengers'] }}</dd></div>
                    <div><dt><x-spec-icon name="bag" width="18" height="18" />{{ $showroom['spec_luggage'] }}</dt><dd>{{ $vehicle->luggage_capacity }} {{ $showroom['luggage_unit'] }}</dd></div>
                    <div><dt><x-spec-icon name="shield" width="18" height="18" />{{ $showroom['spec_insurance'] }}</dt><dd>{{ $vehicle->insurance_included ? $labels['insurance_included'] : $showroom['insurance_excluded'] }}</dd></div>
                    <div>
                        <dt><x-spec-icon name="driver" width="18" height="18" />{{ $showroom['spec_driver'] }}</dt>
                        <dd>
                            @if ($vehicle->driver_available)
                                {{ $showroom['driver_yes'] }}@if ($vehicle->driver_price > 0) (+{{ $rental->currency }} {{ number_format((float) $vehicle->driver_price, 0, ',', '.') }} {{ $labels['per_day'] }})@endif
                            @else
                                {{ $showroom['driver_no'] }}
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (! empty($vehicle->features))
                    <h2 class="detail-heading">{{ $showroom['features'] }}</h2>
                    <ul class="feature-chips">
                        @foreach ($vehicle->features as $feature)
                            <li>{{ $vehicleTerms['feature'][$feature] ?? Str::headline($feature) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <aside class="booking-card" aria-label="{{ $site['booking_title'] }}">
                <h2>{{ $site['booking_title'] }}</h2>
                <p class="price-plate">
                    <small>{{ $labels['from'] }}</small>
                    <strong>{{ $rental->currency }} {{ number_format((float) $vehicle->base_price, 0, ',', '.') }}</strong>
                    <small>{{ $labels['per_day'] }}</small>
                </p>
                <ul class="spec-tiles">
                    <li class="spec-tile"><x-spec-icon name="seat" /><strong>{{ $vehicle->maxPassengers() }}</strong><span>{{ $showroom['spec_seats'] }}</span></li>
                    <li class="spec-tile"><x-spec-icon name="bag" /><strong>{{ $vehicle->luggage_capacity }}</strong><span>{{ $showroom['spec_luggage'] }}</span></li>
                    <li class="spec-tile"><x-spec-icon name="gearbox" /><strong>{{ $vehicleTerms['transmission'][$vehicle->transmission] ?? Str::headline($vehicle->transmission) }}</strong><span>{{ $showroom['spec_transmission'] }}</span></li>
                    <li class="spec-tile"><x-spec-icon name="fuel" /><strong>{{ $vehicleTerms['fuel'][$vehicle->fuel_type] ?? Str::headline($vehicle->fuel_type) }}</strong><span>{{ $showroom['spec_fuel'] }}</span></li>
                </ul>
                <a href="{{ $reserveUrl }}" class="btn btn-primary btn-block">{{ $showroom['reserve_vehicle'] }} <span aria-hidden="true">→</span></a>
                <button type="button" class="btn btn-light btn-block" data-hero-quick-message="{{ str_replace(':name', $vehicleName, $showroom['ask_vehicle_q']) }}">{{ $showroom['ask_vehicle'] }}</button>
                <p class="booking-note">{{ $site['booking_hint'] }}</p>
                <p class="booking-note">{{ $showroom['availability_note'] }}</p>
            </aside>
        </div>
    </section>

    @if ($others->isNotEmpty())
        <section class="section section-tint">
            <div class="container">
                <div class="section-head">
                    <div><h2>{{ $site['related_title'] }}</h2></div>
                    <a href="{{ route('rental.vehicles', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}" class="link-arrow">{{ $site['view_all'] }} <span aria-hidden="true">→</span></a>
                </div>
                <div class="fleet-grid fleet-grid-wide">
                    @foreach ($others as $item)
                        @include('rental.fleet-card', ['item' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
