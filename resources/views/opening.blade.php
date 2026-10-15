@php
    $heroImage = file_exists(public_path('images/hero-car.webp')) ? asset('images/hero-car.webp') : null;
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
<body class="stage stage-opening" data-scene="opening" @if($heroImage) style="--hero-image: url('{{ $heroImage }}')" @endif>
    <div class="stage-bg" aria-hidden="true"></div>

    <header class="stage-top">
        <span class="brand">
            <span class="rental-monogram" aria-hidden="true">F</span>
            <span class="brand-text"><strong>FTS Car Rental AI</strong></span>
        </span>
        <span aria-hidden="true"></span>
        <div class="stage-tools">
            <x-sound-toggle :on="$opening['sound_on']" :off="$opening['sound_off']" />
            <nav aria-label="Language" class="lang-switch">
                @foreach ($supportedLocales as $code)
                    <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="content" class="stage-main">
        <div class="opening">
            <div class="opening-copy">
                <p class="opening-lead"><span>AI Rental Agent</span><i aria-hidden="true"></i></p>
                <h1>{{ $opening['welcome'] }} <em>FTS Car Rental AI</em></h1>
                <p class="opening-tagline">{{ $opening['tagline'] }}</p>
            </div>

            <div class="opening-actions">
                @forelse ($rentals as $rental)
                    <a href="{{ route('rental.show', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}" class="btn btn-primary btn-lg">
                        {{ str_replace(':name', $rental->name, $opening['enter']) }} <span aria-hidden="true">→</span>
                    </a>
                @empty
                    <p class="empty-note">{{ $opening['empty'] }}</p>
                @endforelse
            </div>
        </div>
    </main>
</html>
