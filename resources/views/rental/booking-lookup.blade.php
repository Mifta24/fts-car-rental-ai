@extends('rental.layout')

@section('title', $lines['title'].' · '.$rental->name)

@section('content')
    @include('rental.agent', ['text' => $lines['intro'], 'key' => 'booking'])

    <div class="scene-content">
        <section class="panel panel-fill" aria-label="{{ $lines['title'] }}">
            <div class="panel-head">
                <div>
                    <h1 class="panel-title">{{ $lines['title'] }}</h1>
                    <p class="panel-sub">{{ $lines['lead'] }}</p>
                </div>
            </div>

            @if (session('booking_cancelled'))
                <p class="wizard-hint" role="status">{{ $lines['cancelled_ok'] }}</p>
            @endif

            @if (session('booking_cancel_refused'))
                <p class="wizard-error" role="alert">{{ $lines['cancel_refused'] }}</p>
            @endif

            @if ($booking)
                <dl class="wizard-summary">
                    <div><dt>{{ $lines['reference'] }}</dt><dd>{{ $booking->reference }}</dd></div>
                    <div><dt>{{ $lines['status'] }}</dt><dd>{{ $lines['statuses'][$booking->status] ?? $booking->status }}</dd></div>
                    <div><dt>{{ $lines['vehicle'] }}</dt><dd>{{ $booking->vehicle->translatedName($locale) }}</dd></div>
                    <div><dt>{{ $lines['pickup'] }}</dt><dd>{{ $booking->pickup_date->locale($locale)->translatedFormat('j F Y') }}</dd></div>
                    <div><dt>{{ $lines['return_'] }}</dt><dd>{{ $booking->return_date->locale($locale)->translatedFormat('j F Y') }}</dd></div>
                    <div><dt>{{ $lines['passengers'] }}</dt><dd>{{ $booking->passengers }}</dd></div>
                    <div><dt>{{ $lines['units'] }}</dt><dd>{{ $booking->unit_count }}</dd></div>
                    <div><dt>{{ $lines['driver'] }}</dt><dd>{{ $booking->with_driver ? $lines['with_driver'] : $lines['self_drive'] }}</dd></div>
                    <div><dt>{{ $lines['total'] }}</dt><dd>{{ $rental->currency }} {{ number_format((float) $booking->total_price, 0, ',', '.') }}</dd></div>
                </dl>

                <div style="display:flex;flex-wrap:wrap;gap:12px">
                    @if ($canCancel)
                        <form method="POST" action="{{ route('booking.cancel', ['rentalSlug' => $rental->slug]) }}" onsubmit="return confirm(@js($lines['cancel_confirm']))">
                            @csrf
                            <input type="hidden" name="reference" value="{{ $lookup['reference'] }}">
                            <input type="hidden" name="contact" value="{{ $lookup['contact'] }}">
                            <input type="hidden" name="lang" value="{{ $locale }}">
                            <button type="submit" class="btn btn-ghost btn-sm">{{ $lines['cancel'] }}</button>
                        </form>
                    @endif
                    <a href="{{ route('rental.staff', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}" class="btn btn-ghost btn-sm">{{ $lines['contact_us'] }}</a>
                    <a href="{{ route('rental.booking', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}" class="btn btn-ghost btn-sm">{{ $lines['another'] }}</a>
                </div>
            @else
                @if (session('booking_lookup_failed') || $errors->any())
                    <p class="wizard-error" role="alert">{{ $lines['not_found'] }}</p>
                @endif

                <form method="POST" action="{{ route('booking.lookup', ['rentalSlug' => $rental->slug]) }}" class="wizard-step" autocomplete="off">
                    @csrf
                    <input type="hidden" name="lang" value="{{ $locale }}">
                    <label class="wizard-field">
                        <span>{{ $lines['reference'] }}</span>
                        <input type="text" name="reference" required maxlength="20" placeholder="{{ $lines['reference_ph'] }}" value="{{ old('reference') }}">
                    </label>
                    <label class="wizard-field">
                        <span>{{ $lines['contact'] }}</span>
                        <input type="text" name="contact" required maxlength="120" placeholder="{{ $lines['contact_ph'] }}">
                    </label>
                    <div class="wizard-field-wide">
                        <button type="submit" class="btn btn-primary">{{ $lines['submit'] }}</button>
                    </div>
                </form>
            @endif
        </section>
    </div>
@endsection
