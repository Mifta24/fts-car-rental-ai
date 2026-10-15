@extends('rental.layout')

@php
    $vehicleName = $vehicle->translatedName($locale);
    $primaryImage = $vehicle->images->first();
    $vehicleUrl = fn ($other) => route('rental.vehicle', ['rentalSlug' => $rental->slug, 'vehicleSlug' => $other->slug, 'lang' => $locale]);
    $reserveUrl = route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale, 'vehicle' => $vehicle->slug]);
    $fleetUrl = route('rental.vehicles', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
    $transmission = $vehicleTerms['transmission'][$vehicle->transmission] ?? Str::headline($vehicle->transmission);
    $fuel = $vehicleTerms['fuel'][$vehicle->fuel_type] ?? Str::headline($vehicle->fuel_type);
@endphp

@section('title', $vehicleName.' · '.$rental->name)
@section('description', $vehicle->translatedDescription($locale))

@section('content')
    @include('rental.agent', ['text' => $vehicleNarrations['vehicle'][$vehicle->slug], 'key' => 'vehicle-'.$vehicle->slug])

    <div class="scene-content">
        <section class="panel panel-fill" aria-label="{{ $showroom['vehicle_scene'] }}">
            <div class="vdetail">
                <div data-vehicle-gallery class="vgallery">
                    <div class="vgallery-main">
                        @if ($primaryImage)
                            <img src="{{ $primaryImage->image_source }}" alt="{{ $primaryImage->alt_text }}" data-vehicle-gallery-main>
                        @else
                            <div class="gallery-empty">{{ $showroom['no_photo'] }}</div>
                        @endif
                        @if ($vehicle->body_type)
                            <span class="badge">{{ $vehicleTerms['body'][$vehicle->body_type] ?? Str::headline($vehicle->body_type) }}</span>
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

                <div class="vinfo">
                    <div class="vinfo-top">
                        <p class="eyebrow eyebrow-dark">{{ $showroom['vehicle_counter'] }} {{ $vehicleIndex + 1 }} / {{ $vehicles->count() }}</p>
                        @if ($vehicles->count() > 1)
                            <nav class="vinfo-pager" aria-label="{{ $showroom['vehicle_scene'] }}">
                                <a href="{{ $vehicleUrl($previousVehicle) }}" class="deck-arrow" rel="prev" aria-label="{{ $showroom['prev_vehicle'] }}"><span aria-hidden="true">‹</span></a>
                                <a href="{{ $vehicleUrl($nextVehicle) }}" class="deck-arrow" rel="next" aria-label="{{ $showroom['next_vehicle'] }}"><span aria-hidden="true">›</span></a>
                            </nav>
                        @endif
                    </div>

                    <div class="vinfo-head">
                        <h1>{{ $vehicleName }}</h1>
                        <p class="price-plate">
                            <small>{{ $labels['from'] }}</small>
                            <strong>{{ $rental->currency }} {{ number_format((float) $vehicle->base_price, 0, ',', '.') }}</strong>
                            <small>{{ $labels['per_day'] }}</small>
                        </p>
                    </div>

                    <ul class="spec-tiles">
                        <li class="spec-tile"><x-spec-icon name="seat" /><strong>{{ $vehicle->maxPassengers() }}</strong><span>{{ $showroom['spec_seats'] }}</span></li>
                        <li class="spec-tile"><x-spec-icon name="bag" /><strong>{{ $vehicle->luggage_capacity }}</strong><span>{{ $showroom['spec_luggage'] }}</span></li>
                        <li class="spec-tile"><x-spec-icon name="gearbox" /><strong>{{ $transmission }}</strong><span>{{ $showroom['spec_transmission'] }}</span></li>
                        <li class="spec-tile"><x-spec-icon name="fuel" /><strong>{{ $fuel }}</strong><span>{{ $showroom['spec_fuel'] }}</span></li>
                    </ul>

                    <dl class="spec-table">
                        @if ($vehicle->body_type)
                            <div><dt><x-spec-icon name="body" width="15" height="15" />{{ $showroom['spec_body'] }}</dt><dd>{{ $vehicleTerms['body'][$vehicle->body_type] ?? Str::headline($vehicle->body_type) }}</dd></div>
                        @endif
                        @if ($vehicle->model_year)
                            <div><dt><x-spec-icon name="calendar" width="15" height="15" />{{ $showroom['spec_year'] }}</dt><dd>{{ $vehicle->model_year }}</dd></div>
                        @endif
                        <div><dt><x-spec-icon name="gearbox" width="15" height="15" />{{ $showroom['spec_transmission'] }}</dt><dd>{{ $transmission }}</dd></div>
                        <div><dt><x-spec-icon name="fuel" width="15" height="15" />{{ $showroom['spec_fuel'] }}</dt><dd>{{ $fuel }}</dd></div>
                        <div><dt><x-spec-icon name="seat" width="15" height="15" />{{ $showroom['spec_seats'] }}</dt><dd>{{ $vehicle->maxPassengers() }} {{ $labels['max_passengers'] }}</dd></div>
                        <div><dt><x-spec-icon name="bag" width="15" height="15" />{{ $showroom['spec_luggage'] }}</dt><dd>{{ $vehicle->luggage_capacity }} {{ $showroom['luggage_unit'] }}</dd></div>
                        <div><dt><x-spec-icon name="shield" width="15" height="15" />{{ $showroom['spec_insurance'] }}</dt><dd>{{ $vehicle->insurance_included ? $labels['insurance_included'] : $showroom['insurance_excluded'] }}</dd></div>
                        <div>
                            <dt><x-spec-icon name="driver" width="15" height="15" />{{ $showroom['spec_driver'] }}</dt>
                            <dd>
                                @if ($vehicle->driver_available)
                                    {{ $showroom['driver_yes'] }}@if ($vehicle->driver_price > 0) (+{{ $rental->currency }} {{ number_format((float) $vehicle->driver_price, 0, ',', '.') }}) @endif
                                @else
                                    {{ $showroom['driver_no'] }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if (! empty($vehicle->features))
                        <ul class="feature-chips" aria-label="{{ $showroom['features'] }}">
                            @foreach ($vehicle->features as $feature)
                                <li>{{ $vehicleTerms['feature'][$feature] ?? Str::headline($feature) }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="vinfo-actions">
                        <a href="{{ $reserveUrl }}" class="btn btn-primary">{{ $showroom['reserve_vehicle'] }} <span aria-hidden="true">→</span></a>
                        <button type="button" class="btn btn-light" data-hero-quick-message="{{ str_replace(':name', $vehicleName, $showroom['ask_vehicle_q']) }}">{{ $showroom['ask_vehicle'] }}</button>
                        <a href="{{ $fleetUrl }}" class="btn btn-light">{{ $labels['vehicles_heading'] }}</a>
                    </div>
                    <p class="vinfo-note">{{ $showroom['availability_note'] }}</p>
                </div>
            </div>
        </section>
    </div>
@endsection
