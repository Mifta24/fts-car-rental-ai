@extends('rental.layout')

@php
    $title = $service->translatedTitle($locale);
    $serviceUrl = fn ($item) => route('rental.service', ['rentalSlug' => $rental->slug, 'serviceId' => $item->id, 'lang' => $locale]);
@endphp

@section('title', $title.' · '.$rental->name)

@section('content')
    @include('rental.page-header', ['heading' => $title, 'crumbs' => [$site['nav_services'] => route('rental.services', ['rentalSlug' => $rental->slug, 'lang' => $locale]), $title => null]])

    <section class="section section-flush">
        <div class="container detail-grid detail-grid-narrow">
            <article class="detail-main">
                <div class="detail-pager">
                    <p class="eyebrow eyebrow-dark">{{ $showroom['service_counter'] }} {{ $serviceIndex + 1 }} / {{ $services->count() }}</p>
                    @if ($services->count() > 1)
                        <nav class="pager" aria-label="{{ $labels['menu_services'] }}">
                            <a href="{{ $serviceUrl($previousService) }}" class="btn btn-light btn-sm" rel="prev"><span aria-hidden="true">←</span> {{ $showroom['prev_service'] }}</a>
                            <a href="{{ $serviceUrl($nextService) }}" class="btn btn-light btn-sm" rel="next">{{ $showroom['next_service'] }} <span aria-hidden="true">→</span></a>
                        </nav>
                    @endif
                </div>
                @if ($service->image_url)
                    <div class="service-hero">
                        <img src="{{ $service->image_url }}" alt="{{ $title }}">
                        <span class="fleet-card-badge">{{ $showroom['cat_'.$service->category] ?? Str::headline($service->category) }}</span>
                        <span class="service-card-icon"><x-service-icon :name="$service->iconName()" /></span>
                    </div>
                @endif
                @include('rental.narrator', ['text' => $service->translatedBody($locale), 'key' => 'service-'.$service->id])
                <div class="detail-actions">
                    <button type="button" class="btn btn-primary" data-hero-quick-message="{{ str_replace(':name', $title, $showroom['ask_service_q']) }}">{{ $showroom['ask_service'] }} <span aria-hidden="true">↗</span></button>
                    <a href="{{ route('rental.services', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}" class="btn btn-light">{{ $showroom['back_services'] }}</a>
                </div>
            </article>

            <aside class="side-list" aria-label="{{ $labels['menu_services'] }}">
                <h2>{{ $labels['menu_services'] }}</h2>
                <ul>
                    @foreach ($services as $item)
                        <li><a href="{{ $serviceUrl($item) }}" @if($item->id === $service->id) aria-current="page" @endif><x-service-icon :name="$item->iconName()" width="18" height="18" /><span>{{ $item->translatedTitle($locale) }}</span></a></li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </section>
@endsection
