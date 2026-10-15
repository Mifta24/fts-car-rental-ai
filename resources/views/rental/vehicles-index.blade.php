@extends('rental.layout')

@section('title', $labels['vehicles_heading'].' · '.$rental->name)

@section('content')
    @php $bodyTypes = $vehicles->pluck('body_type')->filter()->unique()->values(); @endphp
    @include('rental.page-header', ['heading' => $labels['vehicles_heading'], 'lead' => $site['page_fleet_lead'], 'crumbs' => [$site['nav_fleet'] => null]])

    <section class="section section-flush fleet-panel">
        <div class="container">
            @if ($vehicleNarrations['vehicles'])
                @include('rental.narrator', ['text' => $vehicleNarrations['vehicles'], 'key' => 'vehicles'])
            @else
                <p class="empty-note">{{ $showroom['vehicles_empty'] }}</p>
            @endif

            @if ($vehicles->isNotEmpty())
                <div class="fleet-toolbar">
                    @if ($bodyTypes->count() > 1)
                        <div class="fleet-filters" role="group" aria-label="{{ $showroom['spec_body'] }}">
                            <button type="button" class="fleet-chip is-active" data-fleet-filter="" aria-pressed="true">{{ $showroom['filter_all'] }}</button>
                            @foreach ($bodyTypes as $type)
                                <button type="button" class="fleet-chip" data-fleet-filter="{{ $type }}" aria-pressed="false">{{ $vehicleTerms['body'][$type] ?? Str::headline($type) }}</button>
                            @endforeach
                        </div>
                    @endif
                    <p class="fleet-count" data-fleet-count data-template="{{ $showroom['fleet_count'] }}">{{ str_replace(':count', (string) $vehicles->count(), $showroom['fleet_count']) }}</p>
                </div>

                <div class="fleet-grid fleet-grid-wide">
                    @foreach ($vehicles as $item)
                        @include('rental.fleet-card', ['item' => $item])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
