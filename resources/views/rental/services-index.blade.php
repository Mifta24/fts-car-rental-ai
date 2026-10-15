@extends('rental.layout')

@section('title', $labels['menu_services'].' · '.$rental->name)

@section('content')
    @include('rental.agent', ['text' => $sceneNarrations['services'] ?? $showroom['empty'], 'key' => 'services'])

    <div class="scene-content">
        <section class="panel panel-fill" aria-label="{{ $labels['menu_services'] }}">
            @if ($services->isEmpty())
                <p class="empty-note">{{ $showroom['empty'] }}</p>
            @else
                <div class="deck" data-deck>
                    <div class="deck-viewport">
                        @foreach ($services->chunk(4) as $page)
                            <div class="deck-page" data-deck-page>
                                <div class="tile-grid">
                                    @foreach ($page as $item)
                                        @include('rental.service-tile', ['item' => $item])
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @include('rental.deck-bar', ['prev' => $showroom['prev_service'], 'next' => $showroom['next_service']])
                </div>
            @endif
        </section>
    </div>
@endsection
