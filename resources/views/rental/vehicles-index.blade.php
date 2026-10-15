@extends('rental.layout')

@section('title', $labels['vehicles_heading'].' · '.$rental->name)

@section('content')
    @include('rental.agent', ['text' => $vehicleNarrations['vehicles'] ?? $showroom['vehicles_empty'], 'key' => 'vehicles'])

    <div class="scene-content">
        <section class="panel panel-fill" aria-label="{{ $labels['vehicles_heading'] }}">
            @if ($vehicles->isEmpty())
                <p class="empty-note">{{ $showroom['vehicles_empty'] }}</p>
            @else
                <div class="deck" data-deck data-deck-narrates>
                    <div class="deck-viewport">
                        @foreach ($vehicles as $item)
                            @include('rental.vehicle-slide', ['item' => $item])
                        @endforeach
                    </div>
                    @include('rental.deck-bar', ['prev' => $showroom['prev_vehicle'], 'next' => $showroom['next_vehicle']])
                </div>
            @endif
        </section>
    </div>
@endsection
