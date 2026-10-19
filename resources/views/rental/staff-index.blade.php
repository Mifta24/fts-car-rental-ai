@extends('rental.layout')

@section('title', $labels['menu_staff'].' · '.$rental->name)

@section('content')
    @include('rental.agent', ['text' => $showroom['staff_intro'], 'key' => 'staff'])

    <div class="scene-content">
        <section class="panel panel-fill" aria-label="{{ $site['contact_title'] }}">
            <div class="panel-head">
                <div>
                    <h1 class="panel-title">{{ $site['contact_title'] }}</h1>
                    <p class="panel-sub">{{ $site['page_contact_lead'] }}</p>
                </div>
            </div>

            <ul class="contact-cards">
                <li>
                    <button type="button" class="contact-card contact-card-agent" style="--i: 0" data-hero-quick-message="{{ $labels['menu_staff_q'] }}">
                        <span class="contact-card-icon"><x-menu-icon name="staff" /></span>
                        <span class="contact-card-text"><strong>{{ $site['contact_chat'] }}</strong><small>{{ $showroom['available'] }}</small></span>
                        <span aria-hidden="true">›</span>
                    </button>
                </li>
                @if ($staffLinks['whatsapp_url'])
                    <li><a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener" class="contact-card" style="--i: 1"><span class="contact-card-icon"><x-info-icon name="contact" /></span><span class="contact-card-text"><strong>{{ $wizard['whatsapp'] }}</strong><small>{{ $rental->whatsapp }}</small></span><span aria-hidden="true">›</span></a></li>
                @endif
                @if ($staffLinks['phone_url'])
                    <li><a href="{{ $staffLinks['phone_url'] }}" class="contact-card" style="--i: 2"><span class="contact-card-icon"><x-info-icon name="contact" /></span><span class="contact-card-text"><strong>{{ $wizard['call_rental'] }}</strong><small>{{ $rental->phone }}</small></span><span aria-hidden="true">›</span></a></li>
                @endif
                @if ($staffLinks['email_url'])
                    <li><a href="{{ $staffLinks['email_url'] }}" class="contact-card" style="--i: 3"><span class="contact-card-icon"><x-info-icon name="about" /></span><span class="contact-card-text"><strong>{{ $wizard['email_rental'] }}</strong><small>{{ $rental->email }}</small></span><span aria-hidden="true">›</span></a></li>
                @endif
                <li><a href="{{ route('rental.booking', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}" class="contact-card" style="--i: 4"><span class="contact-card-icon"><x-info-icon name="about" /></span><span class="contact-card-text"><strong>{{ trans('booking.title', [], $locale) }}</strong><small>{{ trans('booking.lead', [], $locale) }}</small></span><span aria-hidden="true">›</span></a></li>
            </ul>

            <div class="visit-strip">
                <p class="info-address">{{ $site['contact_visit'] }}: {{ $rental->address }}, {{ $rental->city }}, {{ $rental->country }}</p>
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
                <a href="{{ $rental->latitude && $rental->longitude ? 'https://www.google.com/maps?q='.$rental->latitude.','.$rental->longitude : 'https://www.google.com/maps?q='.rawurlencode(trim($rental->address.' '.$rental->city)) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">{{ $showroom['info_map'] }} <span aria-hidden="true">↗</span></a>
            </div>
        </section>
    </div>
@endsection
