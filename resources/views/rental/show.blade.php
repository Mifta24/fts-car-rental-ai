@extends('rental.layout')

@section('content')
    @php
        $tileLabels = ['vehicles' => $labels['menu_vehicles'], 'services' => $labels['menu_services'], 'info' => $labels['menu_policies'], 'reservation' => $showroom['start_booking'], 'staff' => $labels['menu_staff']];
    @endphp

    @include('rental.agent', ['text' => $showroom['welcome'].($locale === 'ja' ? '' : ' ').$showroom['showroom'].($locale === 'ja' ? '。' : '. ').$showroom['intro'], 'key' => 'showroom'])

    <div class="scene-content">
        <section class="panel" aria-labelledby="reception-title">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">{{ str_replace(':city', $rental->city ?: $rental->name, $site['hero_eyebrow']) }}</p>
                    <h1 class="panel-title" id="reception-title">{{ $showroom['explore'] }}</h1>
                </div>
            </div>

            <div class="action-grid">
                @foreach ($menuItems as $item)
                    <a href="{{ $item['href'] }}" class="action-tile" style="--i: {{ $loop->index }}">
                        <span class="action-tile-icon"><x-menu-icon :name="$item['key']" /></span>
                        <span class="action-tile-label">{{ $tileLabels[$item['key']] ?? $item['label'] }} <span aria-hidden="true">→</span></span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
@endsection
