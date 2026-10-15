@php
    $heroImage = file_exists(public_path('images/hero-car.webp'))
        ? asset('images/hero-car.webp')
        : asset('images/vehicles/mercedes-benz-e-class-1.webp');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>FTS Car Rental AI</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage stage-opening" data-scene="opening" style="--hero-image: url('{{ $heroImage }}')">
    <div class="stage-bg" aria-hidden="true"></div>

    <header class="stage-top">
        <nav aria-label="Language" class="lang-switch">
            @foreach ($supportedLocales as $code)
                <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
            @endforeach
        </nav>
        <span class="brand opening-brand">FTS Car Rental AI</span>
        <div class="stage-tools">
            <x-sound-toggle :on="$opening['sound_on']" :off="$opening['sound_off']" />
        </div>
    </header>

    <main id="content" class="stage-main">
        <div class="opening">
            <div class="opening-copy">
                <h1>{{ $opening['welcome'] }} <em>FTS Car Rental AI</em></h1>
                <p class="opening-tagline">{{ $opening['tagline'] }}</p>
            </div>

            <div class="opening-actions">
                @forelse ($rentals as $rental)
                    <a href="{{ route('rental.show', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}" class="opening-go" aria-label="{{ str_replace(':name', $rental->name, $opening['enter']) }}">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    @break
                @empty
                    <p class="empty-note">{{ $opening['empty'] }}</p>
                @endforelse
            </div>
        </div>
    </main>
</body>
</html>
