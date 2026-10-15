@extends('rental.layout')

@php
    $title = $service->translatedTitle($locale);
    $serviceUrl = fn ($item) => route('rental.service', ['rentalSlug' => $rental->slug, 'serviceId' => $item->id, 'lang' => $locale]);
    $servicesUrl = route('rental.services', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
@endphp

@section('title', $title.' · '.$rental->name)

@section('content')
    @include('rental.agent', ['text' => $service->translatedBody($locale), 'key' => 'service-'.$service->id])

    <div class="scene-content">
        <section class="panel panel-fill" aria-label="{{ $title }}">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">{{ $showroom['service_counter'] }} {{ $serviceIndex + 1 }} / {{ $services->count() }}</p>
                    <h1 class="panel-title">{{ $title }}</h1>
                </div>
                @if ($services->count() > 1)
                    <nav class="vinfo-pager" aria-label="{{ $labels['menu_services'] }}">
                        <a href="{{ $serviceUrl($previousService) }}" class="deck-arrow" rel="prev" aria-label="{{ $showroom['prev_service'] }}"><span aria-hidden="true">‹</span></a>
                        <a href="{{ $serviceUrl($nextService) }}" class="deck-arrow" rel="next" aria-label="{{ $showroom['next_service'] }}"><span aria-hidden="true">›</span></a>
                    </nav>
                @endif
            </div>

            @if ($service->image_url)
                <div class="service-hero">
                    <img src="{{ $service->image_url }}" alt="{{ $title }}">
                    <span class="badge">{{ $showroom['cat_'.$service->category] ?? Str::headline($service->category) }}</span>
                    <span class="service-hero-icon"><x-service-icon :name="$service->iconName()" width="26" height="26" /></span>
                </div>
            @else
                <div class="service-hero-plain"><x-service-icon :name="$service->iconName()" /></div>
            @endif

            <p class="service-body">{{ $service->translatedBody($locale) }}</p>

            <div class="service-foot">
                <div class="service-actions">
                    <button type="button" class="btn btn-primary" data-hero-quick-message="{{ str_replace(':name', $title, $showroom['ask_service_q']) }}">{{ $showroom['ask_service'] }} <span aria-hidden="true">↗</span></button>
                    <a href="{{ $servicesUrl }}" class="btn btn-ghost">{{ $showroom['back_services'] }}</a>
                </div>
            </div>
        </section>
    </div>
@endsection
