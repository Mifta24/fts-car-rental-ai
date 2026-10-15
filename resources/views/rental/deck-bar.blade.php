{{-- Controls for a deck: previous / next arrows and dots, filled in by the deck script. --}}
<div class="deck-bar" data-deck-bar>
    <button type="button" class="deck-arrow" data-deck-prev aria-label="{{ $prev }}"><span aria-hidden="true">‹</span></button>
    <div class="deck-mid">
        <div class="deck-dots" data-deck-dots></div>
        <span class="deck-count" data-deck-count aria-live="polite"></span>
    </div>
    <button type="button" class="deck-arrow" data-deck-next aria-label="{{ $next }}"><span aria-hidden="true">›</span></button>
</div>
