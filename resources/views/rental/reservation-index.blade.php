@extends('rental.layout')

@section('title', $showroom['reservation'].' · '.$rental->name)

@section('content')
    @include('rental.page-header', ['heading' => $wizard['title'], 'lead' => $site['page_reserve_lead'], 'crumbs' => [$site['nav_reservation'] => null]])

    <section class="section section-flush">
        <div class="container reserve-grid">
            <div class="wizard-card">
                @include('rental.reservation')
            </div>

            <aside class="booking-card reserve-aside">
                <img src="{{ asset('images/character/agent-tablet.svg') }}" alt="" class="aside-mascot" loading="lazy">
                <h2>{{ $site['reserve_aside_title'] }}</h2>
                <ol class="next-steps">
                    @foreach ([1, 2, 3] as $n)
                        <li><span>{{ $n }}</span>{{ $site['reserve_'.$n] }}</li>
                    @endforeach
                </ol>
                <button type="button" class="btn btn-light btn-block" data-hero-quick-message="{{ $showroom['reservation_q'] }}">{{ $site['reserve_help'] }}</button>
            </aside>
        </div>
    </section>
@endsection
