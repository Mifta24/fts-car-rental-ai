@extends('rental.layout')

@section('title', $showroom['reservation'].' · '.$rental->name)

@section('content')
    @include('rental.agent', ['text' => $showroom['reservation_intro'], 'key' => 'reservation'])

    <div class="scene-content">
        <section class="panel panel-light panel-fill wizard-card" aria-label="{{ $wizard['title'] }}">
            <div class="panel-head">
                <h1 class="panel-title">{{ $wizard['title'] }}</h1>
            </div>
            @include('rental.reservation')
        </section>
    </div>
@endsection
