{{-- The agent's half of a scene: a page index above the agent's line (the narrator), with the character below. --}}
@php
    $sceneNumber = ['showroom' => 1, 'vehicles' => 2, 'vehicle' => 2, 'services' => 3, 'service' => 3, 'info' => 4, 'reservation' => 5, 'staff' => 6][$scene] ?? 1;
    $figures = [
        'reservation' => ['agent-reservation.png', 1145, 1374],
        'services' => ['agent-services.png', 1145, 1374],
        'service' => ['agent-services.png', 1145, 1374],
    ];
    [$figure, $figureWidth, $figureHeight] = $figures[$scene] ?? ['agent-pointing.png', 1536, 1024];
@endphp
<div class="scene-agent">
    <p class="scene-index" aria-hidden="true"><span>{{ sprintf('%03d', $sceneNumber) }}</span><i></i></p>
    @include('rental.narrator', ['text' => $text, 'key' => $key])
    <img src="{{ asset('images/character/'.$figure) }}" alt="{{ $showroom['assistant'] }}" class="agent-figure" width="{{ $figureWidth }}" height="{{ $figureHeight }}" decoding="async">
</div>
