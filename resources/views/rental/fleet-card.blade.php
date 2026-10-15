@php
    $itemName = $item->translatedName($locale);
    $itemUrl = route('rental.vehicle', ['rentalSlug' => $rental->slug, 'vehicleSlug' => $item->slug, 'lang' => $locale]);
    $photo = $item->images->first();
@endphp
<article class="fleet-card" data-fleet-card data-body="{{ $item->body_type }}">
    <a href="{{ $itemUrl }}" class="fleet-card-photo" aria-label="{{ $itemName }}">
        @if ($photo)
            <img src="{{ $photo->image_source }}" alt="{{ $photo->alt_text }}" loading="lazy">
        @endif
        @if ($item->body_type)
            <span class="fleet-card-badge">{{ $vehicleTerms['body'][$item->body_type] ?? Str::headline($item->body_type) }}</span>
        @endif
        @if ($item->insurance_included)
            <span class="fleet-card-badge fleet-card-badge-safe"><x-spec-icon name="shield" width="14" height="14" /> {{ $labels['insurance_included'] }}</span>
        @endif
    </a>
    <div class="fleet-card-body">
        <h3><a href="{{ $itemUrl }}">{{ $itemName }}</a></h3>
        <ul class="fleet-specs">
            <li title="{{ $showroom['spec_seats'] }}"><x-spec-icon name="seat" width="16" height="16" />{{ $item->maxPassengers() }}</li>
            <li title="{{ $showroom['spec_luggage'] }}"><x-spec-icon name="bag" width="16" height="16" />{{ $item->luggage_capacity }}</li>
            <li title="{{ $showroom['spec_transmission'] }}"><x-spec-icon name="gearbox" width="16" height="16" />{{ $vehicleTerms['transmission'][$item->transmission] ?? Str::headline($item->transmission) }}</li>
            <li title="{{ $showroom['spec_fuel'] }}"><x-spec-icon name="fuel" width="16" height="16" />{{ $vehicleTerms['fuel'][$item->fuel_type] ?? Str::headline($item->fuel_type) }}</li>
        </ul>
        <div class="fleet-card-foot">
            <p class="fleet-price"><small>{{ $labels['from'] }}</small> <strong>{{ $rental->currency }} {{ number_format((float) $item->base_price, 0, ',', '.') }}</strong> <small>{{ $labels['per_day'] }}</small></p>
            <div class="fleet-actions">
                <a href="{{ $itemUrl }}" class="fleet-link">{{ $showroom['details'] }}</a>
                <a href="{{ route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale, 'vehicle' => $item->slug]) }}" class="fleet-rent">{{ $showroom['rent'] }}</a>
            </div>
        </div>
    </div>
</article>
