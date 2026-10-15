<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FTS Car Rental AI</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site landing">
    <header class="site-header">
        <div class="container header-inner">
            <span class="brand">
                <span class="rental-monogram" aria-hidden="true">F</span>
                <span class="brand-text"><strong>FTS Car Rental AI</strong></span>
            </span>
            <div class="header-tools">
                <x-sound-toggle :on="$opening['sound_on']" :off="$opening['sound_off']" />
                <nav aria-label="Language" class="lang-switch">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </div>
    </header>

    <main class="landing-main">
        <section class="hero hero-landing">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <p class="eyebrow">AI RENTAL AGENT</p>
                    <h1>{{ $opening['welcome'] }} <em>FTS Car Rental AI</em></h1>
                    <p class="hero-text">{{ $opening['tagline'] }}</p>

                    @if ($rentals->count() === 1)
                        @php $rentalSlug = $rentals->first()->slug; @endphp
                        <ul class="landing-links">
                            @foreach (['vehicles' => 'rental.vehicles', 'services' => 'rental.services', 'reservation' => 'rental.reservation', 'staff' => 'rental.staff'] as $key => $routeName)
                                <li><a href="{{ route($routeName, ['rentalSlug' => $rentalSlug, 'lang' => $locale]) }}"><span>{{ $opening[$key] }}</span><span aria-hidden="true">›</span></a></li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="hero-actions">
                        @forelse ($rentals as $rental)
                            <a href="{{ route('rental.show', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}" class="btn btn-primary">
                                {{ str_replace(':name', $rental->name, $opening['enter']) }} <span aria-hidden="true">→</span>
                            </a>
                        @empty
                            <p class="empty-note empty-note-dark">{{ $opening['empty'] }}</p>
                        @endforelse
                    </div>
                </div>
                <div class="hero-art">
                    <img src="{{ asset('images/hero.webp') }}" alt="" class="hero-art-image" fetchpriority="high">
                    <img src="{{ asset('images/character/agent-presenting.svg') }}" alt="" class="hero-mascot">
                </div>
            </div>
        </section>
    </main>
</body>
</html>
