{{-- The shared site shell: sticky header, page content, footer and the floating AI Rental Agent chat widget. --}}
@php
    $pageTitle = trim($__env->yieldContent('title')) ?: null;
    $pageDescription = trim($__env->yieldContent('description')) ?: null;
    $navScene = ['showroom' => 'home', 'vehicles' => 'fleet', 'vehicle' => 'fleet', 'services' => 'services', 'service' => 'services', 'info' => 'info', 'reservation' => 'reservation', 'staff' => 'contact'][$scene] ?? 'home';
    $navItems = [
        'home' => [$site['nav_home'], route('rental.show', ['rentalSlug' => $rental->slug, 'lang' => $locale])],
        'fleet' => [$site['nav_fleet'], route('rental.vehicles', ['rentalSlug' => $rental->slug, 'lang' => $locale])],
        'services' => [$site['nav_services'], route('rental.services', ['rentalSlug' => $rental->slug, 'lang' => $locale])],
        'info' => [$site['nav_info'], route('rental.info', ['rentalSlug' => $rental->slug, 'lang' => $locale])],
        'contact' => [$site['nav_contact'], route('rental.staff', ['rentalSlug' => $rental->slug, 'lang' => $locale])],
    ];
    $reservationUrl = route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $pageDescription ?? $rental->translatedDescription($locale) }}">
    <title>{{ $pageTitle ?? $rental->name.' · '.$labels['chat_heading'] }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site" data-showroom data-scene="{{ $scene }}" style="--agent-image: url('{{ $backdrop['avatarImage'] }}'); --agent-zoom: {{ $backdrop['avatarZoom'] }}; --agent-focus: {{ $backdrop['avatarFocus'] }}">
    <a href="#content" class="skip-link">{{ $site['skip'] }}</a>

    <header class="site-header" data-site-header>
        <div class="container header-inner">
            <a href="{{ $navItems['home'][1] }}" class="brand">
                <span class="rental-monogram" aria-hidden="true">{{ mb_substr($rental->name, 0, 1) }}</span>
                <span class="brand-text"><strong>{{ $rental->name }}</strong><small>{{ $rental->city }}</small></span>
            </a>

            <nav class="site-nav" id="site-nav" aria-label="{{ $site['nav_label'] }}">
                @foreach ($navItems as $key => [$label, $href])
                    <a href="{{ $href }}" class="site-nav-link" @if($key === $navScene) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <a href="{{ $reservationUrl }}" class="btn btn-primary site-nav-cta">{{ $site['cta_rent'] }}</a>
            </nav>

            <div class="header-tools">
                <x-sound-toggle :on="$showroom['sound_on']" :off="$showroom['sound_off']" />
                <nav aria-label="Language" class="lang-switch">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
                <a href="{{ $reservationUrl }}" class="btn btn-primary btn-sm header-cta">{{ $site['cta_rent'] }}</a>
                <button type="button" class="nav-toggle" data-nav-toggle aria-controls="site-nav" aria-expanded="false" aria-label="{{ $site['menu'] }}"><span></span><span></span><span></span></button>
            </div>
        </div>
    </header>

    <main id="content" class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div class="footer-brand">
                <a href="{{ $navItems['home'][1] }}" class="brand">
                    <span class="rental-monogram" aria-hidden="true">{{ mb_substr($rental->name, 0, 1) }}</span>
                    <span class="brand-text"><strong>{{ $rental->name }}</strong><small>{{ $rental->city }}</small></span>
                </a>
                @if ($rental->translatedDescription($locale))
                    <p>{{ $rental->translatedDescription($locale) }}</p>
                @endif
            </div>
            <nav aria-label="{{ $site['footer_links'] }}">
                <h2>{{ $site['footer_links'] }}</h2>
                <ul>
                    @foreach ($navItems as [$label, $href])
                        <li><a href="{{ $href }}">{{ $label }}</a></li>
                    @endforeach
                    <li><a href="{{ $reservationUrl }}">{{ $site['nav_reservation'] }}</a></li>
                </ul>
            </nav>
            <div>
                <h2>{{ $site['footer_contact'] }}</h2>
                <ul class="footer-contact">
                    @if ($rental->address)<li>{{ $rental->address }}, {{ $rental->city }}</li>@endif
                    @if ($rental->phone)<li><a href="{{ $staffLinks['phone_url'] }}">{{ $rental->phone }}</a></li>@endif
                    @if ($staffLinks['whatsapp_url'])<li><a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener">WhatsApp</a></li>@endif
                    @if ($rental->email)<li><a href="mailto:{{ $rental->email }}">{{ $rental->email }}</a></li>@endif
                </ul>
            </div>
            <div>
                <h2>{{ $site['footer_hours'] }}</h2>
                <p class="footer-hours">{{ substr($rental->open_time ?? '', 0, 5) ?: '—' }} – {{ substr($rental->close_time ?? '', 0, 5) ?: '—' }}</p>
                <p class="footer-note">{{ $showroom['available'] }}</p>
            </div>
        </div>
        <div class="container footer-bottom">
            <span>© {{ date('Y') }} {{ $rental->name }}</span>
            <span>POWERED BY FTS</span>
        </div>
    </footer>

    <div data-chat-widget>
        @include('rental.chat')
    </div>
    <noscript><p class="noscript-note">{{ $site['noscript'] }} {{ $rental->phone }}</p></noscript>
</body>
</html>
