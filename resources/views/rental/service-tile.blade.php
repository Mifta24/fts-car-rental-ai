<a href="{{ route('rental.service', ['rentalSlug' => $rental->slug, 'serviceId' => $item->id, 'lang' => $locale]) }}" class="service-tile">
    @if ($item->image_url)
        <span class="service-tile-photo"><img src="{{ $item->image_url }}" alt="" loading="lazy"></span>
    @endif
    <span class="badge">{{ $showroom['cat_'.$item->category] ?? Str::headline($item->category) }}</span>
    <span class="service-tile-icon"><x-service-icon :name="$item->iconName()" width="20" height="20" /></span>
    <span class="service-tile-title">{{ $item->translatedTitle($locale) }}</span>
    <span class="service-tile-text">{{ $item->translatedBody($locale) }}</span>
</a>
