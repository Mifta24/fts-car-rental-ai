{{-- The agent's half of a scene: her speech bubble (the narrator) above her standing figure. --}}
<div class="scene-agent">
    @include('rental.narrator', ['text' => $text, 'key' => $key])
    <img src="{{ $backdrop['character'] }}" alt="{{ $showroom['assistant'] }}" @class(['agent-figure', 'is-mirrored' => str_contains($backdrop['character'], 'presenting')])>
</div>
