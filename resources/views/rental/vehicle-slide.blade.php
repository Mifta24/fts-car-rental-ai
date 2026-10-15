@php
    $itemName = $item->translatedName($locale);
    $itemUrl = route('rental.vehicle', ['rentalSlug' => $rental->slug, 'vehicleSlug' => $item->slug, 'lang' => $locale]);
    $photo = $item->images->first();
@endphp
<article class="deck-page" data-deck-page data-narration="{{ $vehicleNarrations['vehicle'][$item->slug] ?? '' }}" aria-label="{{ $itemName }}">
    <a href="{{ $itemUrl }}" class="vslide-photo" aria-label="{{ $itemName }}" tabindex="-1">
        @if ($photo)
            <img src="{{ $photo->image_source }}" alt="{{ $photo->alt_text }}" @if(! $loop->first) loading="lazy" @endif>
        @else
            <span class="vslide-empty">{{ $showroom['no_photo'] }}</span>
        @endif
        @if ($item->body_type)
            <span class="badge">{{ $vehicleTerms['body'][$item->body_type] ?? Str::headline($item->body_type) }}</span>
        @endif
        @if ($item->insurance_included)
            <span class="badge"><x-spec-icon name="shield" width="14" height="14" /> {{ $labels['insurance_included'] }}</span>
        @endif
    </a>
    <div class="vslide-foot">
        <div>
            <h2 class="vslide-name"><a href="{{ $itemUrl }}">{{ $itemName }}</a></h2>
            <ul class="spec-line">
                <li title="{{ $showroom['spec_seats'] }}"><x-spec-icon name="seat" width="16" height="16" />{{ $item->maxPassengers() }}</li>
                <li title="{{ $showroom['spec_luggage'] }}"><x-spec-icon name="bag" width="16" height="16" />{{ $item->luggage_capacity }}</li>
                <li title="{{ $showroom['spec_transmission'] }}"><x-spec-icon name="gearbox" width="16" height="16" />{{ $vehicleTerms['transmission'][$item->transmission] ?? Str::headline($item->transmission) }}</li>
                <li title="{{ $showroom['spec_fuel'] }}"><x-spec-icon name="fuel" width="16" height="16" />{{ $vehicleTerms['fuel'][$item->fuel_type] ?? Str::headline($item->fuel_type) }}</li>
            </ul>
        </div>
        <div class="vslide-buy">
            <p class="price-plate"><small>{{ $labels['from'] }}</small> <strong>{{ $rental->currency }} {{ number_format((float) $item->base_price, 0, ',', '.') }}</strong> <small>{{ $labels['per_day'] }}</small></p>
            <div class="vslide-actions">
                <a href="{{ $itemUrl }}" class="btn btn-ghost btn-sm">{{ $showroom['details'] }}</a>
                <a href="{{ route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale, 'vehicle' => $item->slug]) }}" class="btn btn-primary btn-sm">{{ $showroom['rent'] }}</a>
            </div>
        </div>
    </div>
</article>
