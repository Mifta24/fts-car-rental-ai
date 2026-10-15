{{-- The stage shell: one fixed screen with a scene backdrop, a slim top bar, the scene itself (agent + content) and the always-on "ask the agent" dock. Nothing here scrolls. --}}
@php
    $pageTitle = trim($__env->yieldContent('title')) ?: null;
    $pageDescription = trim($__env->yieldContent('description')) ?: null;
    $navScene = ['showroom' => 'home', 'vehicles' => 'fleet', 'vehicle' => 'fleet', 'services' => 'services', 'service' => 'services', 'info' => 'info', 'reservation' => 'reservation', 'staff' => 'contact'][$scene] ?? 'home';
    $navItems = [
        'home' => [$site['nav_home'], route('rental.show', ['rentalSlug' => $rental->slug, 'lang' => $locale]), 'home'],
        'fleet' => [$site['nav_fleet'], route('rental.vehicles', ['rentalSlug' => $rental->slug, 'lang' => $locale]), 'vehicles'],
        'services' => [$site['nav_services'], route('rental.services', ['rentalSlug' => $rental->slug, 'lang' => $locale]), 'services'],
        'info' => [$site['nav_info'], route('rental.info', ['rentalSlug' => $rental->slug, 'lang' => $locale]), 'info'],
        'reservation' => [$site['nav_reservation'], route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale]), 'reservation'],
        'contact' => [$site['nav_contact'], route('rental.staff', ['rentalSlug' => $rental->slug, 'lang' => $locale]), 'staff'],
    ];
    $backdropImage = ['showroom' => 'showroom', 'vehicles' => 'fleet', 'vehicle' => 'fleet', 'services' => 'services', 'service' => 'services', 'info' => 'info', 'reservation' => 'reservation', 'staff' => 'staff'][$scene] ?? 'showroom';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $pageDescription ?? $rental->translatedDescription($locale) }}">
    <title>{{ $pageTitle ?? $rental->name.' · '.$labels['chat_heading'] }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage" data-showroom data-scene="{{ $scene }}" style="--scene-image: url('{{ asset('images/scenes/'.$backdropImage.'.webp') }}'); --agent-image: url('{{ $backdrop['avatarImage'] }}'); --agent-zoom: {{ $backdrop['avatarZoom'] }}; --agent-focus: {{ $backdrop['avatarFocus'] }}">
    <a href="#content" class="skip-link">{{ $site['skip'] }}</a>
    <div class="stage-bg" aria-hidden="true"></div>

    <header class="stage-top">
        <a href="{{ $navItems['home'][1] }}" class="brand">
            <span class="rental-monogram" aria-hidden="true">{{ mb_substr($rental->name, 0, 1) }}</span>
            <span class="brand-text"><strong>{{ $rental->name }}</strong><small>{{ $rental->city }}</small></span>
        </a>

        <nav class="stage-nav" aria-label="{{ $site['nav_label'] }}">
            @foreach ($navItems as $key => [$label, $href, $icon])
                <a href="{{ $href }}" class="stage-nav-link" @if($key === $navScene) aria-current="page" @endif>
                    <x-menu-icon :name="$icon" width="18" height="18" /><span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>

        <div class="stage-tools">
            <x-sound-toggle :on="$showroom['sound_on']" :off="$showroom['sound_off']" />
            <nav aria-label="Language" class="lang-switch">
                @foreach ($supportedLocales as $code)
                    <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="content" class="stage-main">
        <div class="scene scene-{{ $scene }}">
            @yield('content')
        </div>
    </main>

    <form class="ask-dock" data-ask-form autocomplete="off">
        <div class="ask-dock-inner">
            <span class="chat-host-avatar" aria-hidden="true"></span>
            <input type="text" class="ask-dock-input" data-ask-input maxlength="4000" aria-label="{{ $labels['chat_placeholder'] }}" placeholder="{{ $labels['chat_placeholder'] }}">
            <button type="submit" class="ask-dock-send" data-ask-send>{{ $labels['chat_send'] }}</button>
        </div>
    </form>

    <div data-chat-widget>
        @include('rental.chat')
    </div>
    <noscript><p class="noscript-note">{{ $site['noscript'] }} {{ $rental->phone }}</p></noscript>
</body>
</html>
