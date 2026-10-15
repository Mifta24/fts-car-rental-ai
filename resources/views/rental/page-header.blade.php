{{-- Inner-page banner: breadcrumb, heading, short lead and, optionally, the agent's spoken intro. --}}
<section class="page-hero">
    <div class="container page-hero-inner">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('rental.show', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}">{{ $site['crumb_home'] }}</a>
            @foreach ($crumbs as $crumbLabel => $crumbUrl)
                <span aria-hidden="true">/</span>
                @if ($crumbUrl)
                    <a href="{{ $crumbUrl }}">{{ $crumbLabel }}</a>
                @else
                    <span aria-current="page">{{ $crumbLabel }}</span>
                @endif
            @endforeach
        </nav>
        <h1>{{ $heading }}</h1>
        @isset($lead)
            <p class="page-lead">{{ $lead }}</p>
        @endisset
    </div>
</section>
