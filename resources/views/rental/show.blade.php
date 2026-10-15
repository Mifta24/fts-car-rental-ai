@extends('rental.layout')

@section('content')
    @php
        $reservationUrl = route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $fleetUrl = route('rental.vehicles', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $servicesUrl = route('rental.services', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
    @endphp

    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow">{{ str_replace(':city', $rental->city ?: $rental->name, $site['hero_eyebrow']) }}</p>
                <h1>{{ $site['hero_title'] }} <em>{{ $site['hero_title_em'] }}</em></h1>
                <p class="hero-text">{{ $site['hero_text'] }}</p>
                <div class="hero-actions">
                    <a href="{{ $fleetUrl }}" class="btn btn-primary">{{ $site['hero_cta_fleet'] }} <span aria-hidden="true">→</span></a>
                    <button type="button" class="btn btn-ghost" data-hero-quick-message="{{ $showroom['reservation_q'] }}">{{ $site['hero_cta_agent'] }}</button>
                </div>
                <ul class="hero-badges">
                    <li><x-spec-icon name="gearbox" width="18" height="18" />{{ $site['badge_agent'] }}</li>
                    <li><x-spec-icon name="shield" width="18" height="18" />{{ $site['badge_price'] }}</li>
                    <li><x-spec-icon name="driver" width="18" height="18" />{{ $site['badge_staff'] }}</li>
                </ul>
            </div>

            <div class="hero-art" aria-hidden="false">
                <img src="{{ asset('images/hero.webp') }}" alt="" class="hero-art-image" fetchpriority="high">
                <p class="hero-bubble">{{ $site['hero_bubble'] }}</p>
                <img src="{{ $backdrop['character'] }}" alt="{{ $showroom['assistant'] }}" class="hero-mascot">
            </div>
        </div>

        <div class="container">
            <form class="search-card" method="GET" action="{{ route('rental.reservation', ['rentalSlug' => $rental->slug]) }}">
                <input type="hidden" name="lang" value="{{ $locale }}">
                <p class="search-card-title"><x-menu-icon name="vehicles" />{{ $showroom['search_title'] }}</p>
                <label class="search-field">
                    <span>{{ $wizard['pickup_date'] }}</span>
                    <input type="date" name="pickup_date" min="{{ $today }}" required>
                </label>
                <label class="search-field">
                    <span>{{ $wizard['return_date'] }}</span>
                    <input type="date" name="return_date" min="{{ $today }}" required>
                </label>
                <label class="search-field search-field-small">
                    <span>{{ $wizard['passengers'] }}</span>
                    <input type="number" name="passengers" min="1" max="20" value="2" inputmode="numeric" required>
                </label>
                <button type="submit" class="btn btn-primary search-submit">{{ $showroom['search_cta'] }} <span aria-hidden="true">→</span></button>
            </form>
        </div>
    </section>

    @if ($vehicles->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-head">
                    <div>
                        <h2>{{ $site['fleet_title'] }}</h2>
                        <p>{{ $site['fleet_sub'] }}</p>
                    </div>
                    <a href="{{ $fleetUrl }}" class="link-arrow">{{ $site['view_all'] }} <span aria-hidden="true">→</span></a>
                </div>
                <div class="fleet-grid fleet-grid-wide">
                    @foreach ($vehicles->take(3) as $item)
                        @include('rental.fleet-card', ['item' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section section-tint">
        <div class="container">
            <div class="section-head section-head-center">
                <div>
                    <h2>{{ $site['how_title'] }}</h2>
                    <p>{{ $site['how_sub'] }}</p>
                </div>
            </div>
            <ol class="steps">
                @foreach ([1 => 'vehicles', 2 => 'reservation', 3 => 'staff'] as $n => $icon)
                    <li class="step">
                        <span class="step-number">{{ $n }}</span>
                        <x-menu-icon :name="$icon" class="step-icon" />
                        <h3>{{ $site['step_'.$n.'_title'] }}</h3>
                        <p>{{ $site['step_'.$n.'_text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="section">
        <div class="container why">
            <div class="why-copy">
                <h2>{{ $site['why_title'] }}</h2>
                <p>{{ $site['why_sub'] }}</p>
                <button type="button" class="btn btn-primary" data-hero-quick-message="{{ $labels['menu_vehicles_q'] }}">{{ $site['why_cta'] }}</button>
            </div>
            <ul class="benefits">
                @foreach ([1 => 'calendar', 2 => 'gearbox', 3 => 'driver', 4 => 'shield'] as $n => $icon)
                    <li class="benefit">
                        <span class="benefit-icon"><x-spec-icon :name="$icon" /></span>
                        <h3>{{ $site['why_'.$n.'_title'] }}</h3>
                        <p>{{ $site['why_'.$n.'_text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($services->isNotEmpty())
        <section class="section section-tint">
            <div class="container">
                <div class="section-head">
                    <div>
                        <h2>{{ $site['services_title'] }}</h2>
                        <p>{{ $site['services_sub'] }}</p>
                    </div>
                    <a href="{{ $servicesUrl }}" class="link-arrow">{{ $site['view_all'] }} <span aria-hidden="true">→</span></a>
                </div>
                <div class="service-grid">
                    @foreach ($services->take(4) as $item)
                        @include('rental.service-card', ['item' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="cta-band">
        <div class="container cta-inner">
            <img src="{{ asset('images/character/agent-tablet.svg') }}" alt="" class="cta-mascot" loading="lazy">
            <div>
                <h2>{{ $site['cta_title'] }}</h2>
                <p>{{ $site['cta_text'] }}</p>
            </div>
            <div class="cta-actions">
                <button type="button" class="btn btn-primary" data-hero-quick-message="{{ $showroom['reservation_q'] }}">{{ $site['cta_button'] }}</button>
                <a href="{{ $reservationUrl }}" class="btn btn-ghost">{{ $site['nav_reservation'] }}</a>
            </div>
        </div>
    </section>
@endsection
