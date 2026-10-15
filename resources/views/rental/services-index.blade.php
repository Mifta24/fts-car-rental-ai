@extends('rental.layout')

@section('title', $labels['menu_services'].' · '.$rental->name)

@section('content')
    @include('rental.page-header', ['heading' => $labels['menu_services'], 'lead' => $site['page_services_lead'], 'crumbs' => [$site['nav_services'] => null]])

    <section class="section section-flush">
        <div class="container">
            @if ($sceneNarrations['services'])
                @include('rental.narrator', ['text' => $sceneNarrations['services'], 'key' => 'services'])
            @endif

            <div class="service-grid service-grid-wide">
                @forelse ($services as $item)
                    @include('rental.service-card', ['item' => $item])
                @empty
                    <p class="empty-note">{{ $showroom['empty'] }}</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
