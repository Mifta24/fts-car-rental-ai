{{-- The agent's half of a scene: a page index above her line (the narrator).
     The character is not shown for now. To bring it back, place the image in `.scene-agent`
     below the narrator, e.g. <img src="{{ asset('images/character/your-character.png') }}" alt="{{ $showroom['assistant'] }}" class="agent-figure">. --}}
@php
    $sceneNumber = ['showroom' => 1, 'vehicles' => 2, 'vehicle' => 2, 'services' => 3, 'service' => 3, 'info' => 4, 'reservation' => 5, 'staff' => 6][$scene] ?? 1;
@endphp
<div class="scene-agent">
    <p class="scene-index" aria-hidden="true"><span>{{ sprintf('%03d', $sceneNumber) }}</span><i></i></p>
    @include('rental.narrator', ['text' => $text, 'key' => $key])
</div>
