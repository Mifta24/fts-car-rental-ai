<a href="{{ route('rental.service', ['rentalSlug' => $rental->slug, 'serviceId' => $item->id, 'lang' => $locale]) }}" class="service-card">
    <span class="service-card-photo">
        @if ($item->image_url)
            <img src="{{ $item->image_url }}" alt="" loading="lazy">
        @endif
        <span class="fleet-card-badge">{{ $showroom['cat_'.$item->category] ?? Str::headline($item->category) }}</span>
        <span class="service-card-icon"><x-service-icon :name="$item->iconName()" /></span>
    </span>
    <span class="service-card-body">
        <span class="service-card-title">{{ $item->translatedTitle($locale) }}</span>
        <span class="service-card-text">{{ $item->translatedBody($locale) }}</span>
    </span>
    <span class="service-card-go" aria-hidden="true">›</span>
</a>
