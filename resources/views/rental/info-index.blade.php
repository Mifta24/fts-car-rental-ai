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
    @include('rental.agent', ['text' => $sceneNarrations['info'], 'key' => 'info'])

    <div class="scene-content">
        <section class="panel panel-fill" aria-label="{{ $showroom['menu_info'] }}">
            <div class="panel-head">
                <div>
                    <h1 class="panel-title">{{ $showroom['menu_info'] }}</h1>
                    @if ($about)
                        <p class="info-about">{{ $about }}</p>
                    @endif
                </div>
            </div>

            <div class="info-facts">
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
                <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="info-fact"><x-info-icon name="place" /><span>{{ $rental->city ?: $showroom['info_address'] }} · {{ $showroom['info_map'] }}</span></a>
                @if ($staffLinks['whatsapp_url'])
                    <a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener" class="info-fact"><x-info-icon name="contact" /><span>{{ $wizard['whatsapp'] }}</span></a>
                @elseif ($rental->phone)
                    <a href="{{ $staffLinks['phone_url'] }}" class="info-fact"><x-info-icon name="contact" /><span>{{ $rental->phone }}</span></a>
                @endif
            </div>

            @if ($sections->isNotEmpty())
                <div class="info-tabs">
                    @foreach ($sections as $category => $section)
                        <input type="radio" name="info-tab" id="info-tab-{{ $category }}" class="info-tab-radio" @checked($loop->first)>
                    @endforeach

                    @if ($sections->count() > 1)
                        <div class="info-tablist">
                            @foreach ($sections as $category => $section)
                                <label for="info-tab-{{ $category }}">{{ $section['tab'] }}</label>
                            @endforeach
                        </div>
                    @endif

                    <div class="info-tabpanels">
                        @foreach ($sections as $category => $section)
                            <div class="info-tabpanel" data-panel="{{ $category }}" role="group" aria-label="{{ $section['heading'] }}">
                                <div class="deck" data-deck>
                                    <div class="deck-viewport">
                                        @foreach ($infoItems[$category]->chunk(3) as $page)
                                            <div class="deck-page" data-deck-page>
                                                <div class="info-page">
                                                    @foreach ($page as $item)
                                                        <article class="info-card">
                                                            <h3>{{ $item->translatedTitle($locale) }}</h3>
                                                            <p>{{ $item->translatedBody($locale) }}</p>
                                                        </article>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    @include('rental.deck-bar', ['prev' => $showroom['prev_service'], 'next' => $showroom['next_service']])
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="service-foot">
                <button type="button" data-hero-quick-message="{{ $labels['menu_policies_q'] }}" class="btn btn-primary">{{ $showroom['info_ask'] }} <span aria-hidden="true">↗</span></button>
            </div>
        </section>
    </div>
@endsection
