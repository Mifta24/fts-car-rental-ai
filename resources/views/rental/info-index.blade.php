@extends('rental.layout')

@php
    $about = $rental->translatedDescription($locale);
    $mapUrl = $rental->latitude && $rental->longitude
        ? 'https://www.google.com/maps?q='.$rental->latitude.','.$rental->longitude
        : 'https://www.google.com/maps?q='.rawurlencode(trim($rental->address.' '.$rental->city));
    $sections = collect([
        'general' => ['tab' => $showroom['info_tab_about'], 'heading' => $showroom['info_about']],
        'policies' => ['tab' => $showroom['info_policies'], 'heading' => $showroom['info_policies']],
        'faq' => ['tab' => $showroom['info_tab_faq'], 'heading' => $showroom['info_faq']],
    ])->filter(fn ($section, $category) => ($infoItems[$category] ?? collect())->isNotEmpty());
@endphp

@section('title', $showroom['menu_info'].' · '.$rental->name)

@section('content')
    @include('rental.page-header', ['heading' => $showroom['menu_info'], 'lead' => $site['page_info_lead'], 'crumbs' => [$site['nav_info'] => null]])

    <section class="section section-flush">
        <div class="container detail-grid detail-grid-narrow info-layout">
            <div class="detail-main">
                @include('rental.narrator', ['text' => $sceneNarrations['info'], 'key' => 'info'])

                @if ($about)
                    <p class="info-about">{{ $about }}</p>
                @endif

                @if ($sections->count() > 1)
                    <div class="info-tabs">
                        @foreach ($sections as $category => $section)
                            <input type="radio" name="info-tab" id="info-tab-{{ $category }}" class="info-tab-radio" @checked($loop->first)>
                        @endforeach

                        <div class="info-tablist">
                            @foreach ($sections as $category => $section)
                                <label for="info-tab-{{ $category }}">{{ $section['tab'] }}</label>
                            @endforeach
                        </div>

                        <div class="info-tabpanels">
                            @foreach ($sections as $category => $section)
                                <div class="info-tabpanel" data-panel="{{ $category }}">
                                    <div class="info-list">
                                        @foreach ($infoItems[$category] as $item)
                                            <details class="info-row">
                                                <summary><span>{{ $item->translatedTitle($locale) }}</span><span aria-hidden="true" class="info-row-chevron">›</span></summary>
                                                <p>{{ $item->translatedBody($locale) }}</p>
                                            </details>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif ($sections->isNotEmpty())
                    @php $only = $sections->keys()->first(); @endphp
                    <h2 class="detail-heading">{{ $sections[$only]['heading'] }}</h2>
                    <div class="info-list">
                        @foreach ($infoItems[$only] as $item)
                            <details class="info-row">
                                <summary><span>{{ $item->translatedTitle($locale) }}</span><span aria-hidden="true" class="info-row-chevron">›</span></summary>
                                <p>{{ $item->translatedBody($locale) }}</p>
                            </details>
                        @endforeach
                    </div>
                @endif

                <div class="detail-actions">
                    <button type="button" data-hero-quick-message="{{ $labels['menu_policies_q'] }}" class="btn btn-primary">{{ $showroom['info_ask'] }} <span aria-hidden="true">↗</span></button>
                </div>
            </div>

            <aside class="booking-card info-card">
                <h2>{{ $showroom['info_hours'] }}</h2>
                <div class="info-hours">
                    <div class="info-hours-item">
                        <span class="info-hours-label">{{ $showroom['hours_open'] }}</span>
                        <span class="info-hours-time">{{ substr($rental->open_time ?? '', 0, 5) ?: '—' }}</span>
                    </div>
                    <div class="info-hours-divider" aria-hidden="true"></div>
                    <div class="info-hours-item">
                        <span class="info-hours-label">{{ $showroom['hours_close'] }}</span>
                        <span class="info-hours-time">{{ substr($rental->close_time ?? '', 0, 5) ?: '—' }}</span>
                    </div>
                </div>
                <ul class="info-facts">
                    <li><a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="info-fact"><x-info-icon name="place" /><span>{{ $rental->city ?: $showroom['info_address'] }}</span></a></li>
                    @if ($staffLinks['whatsapp_url'])
                        <li><a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener" class="info-fact"><x-info-icon name="contact" /><span>{{ $wizard['whatsapp'] }}</span></a></li>
                    @elseif ($rental->phone)
                        <li><a href="{{ $staffLinks['phone_url'] }}" class="info-fact"><x-info-icon name="contact" /><span>{{ $rental->phone }}</span></a></li>
                    @endif
                </ul>
                <p class="info-address">{{ $rental->address }}, {{ $rental->city }}, {{ $rental->country }}</p>
            </aside>
        </div>
    </section>
@endsection
