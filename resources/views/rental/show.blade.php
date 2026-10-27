@extends('rental.layout')

@section('content')
    @php
        $tileLabels = ['vehicles' => $labels['menu_vehicles'], 'services' => $labels['menu_services'], 'info' => $labels['menu_policies'], 'reservation' => $showroom['start_booking'], 'staff' => $labels['menu_staff']];
        $intro = $showroom['welcome'].($locale === 'ja' ? '' : ' ').$showroom['showroom'].($locale === 'ja' ? '。' : '. ').$showroom['intro'];
    @endphp

    <section class="hero" aria-labelledby="hero-title">
        <p class="hero-place"><span>{{ str_replace(':city', $rental->city ?: $rental->name, $site['hero_eyebrow']) }}</span><i aria-hidden="true"></i></p>

        <div class="hero-note">
            @include('rental.narrator', ['text' => $intro, 'key' => 'showroom'])
        </div>

        <img src="{{ asset('images/character/agent-hero.png') }}" alt="{{ $showroom['assistant'] }}" class="hero-figure" width="1312" height="1199" decoding="async">

        <p class="hero-index" aria-hidden="true">001</p>

        <h1 class="hero-title" id="hero-title">
            <span><b>{{ $site['hero_title'] }}</b></span>
            <span><b>{{ $site['hero_title_em'] }}</b></span>
        </h1>
    </section>

    <nav class="hero-ways" aria-label="{{ $showroom['explore'] }}">
        @foreach ($menuItems as $item)
            <a href="{{ $item['href'] }}" @class(['hero-way', 'hero-way-main' => $item['key'] === 'reservation'])>
                <span>{{ $tileLabels[$item['key']] ?? $item['label'] }}</span>
                <span aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>
@endsection
