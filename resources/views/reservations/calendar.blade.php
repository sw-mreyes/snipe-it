@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('reservations.calendar') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>
            @include('reservations.partials.toolbar', ['active' => 'calendar'])

            {{-- FullCalendar mounts here; initialized in the bundled JS.
                 Data is loaded from the reservations API. An optional ?highlight=<id>
                 query param highlights a specific reservation (e.g. linked from the
                 detail/edit page). --}}
            <div id="reservations-calendar"
                 data-events-url="{{ route('api.reservations.index') }}"
                 data-highlight-id="{{ request('highlight') }}"></div>

            {{-- Populated by the JS on event click. --}}
            <div id="reservation-event-details" style="margin-top: 15px;"></div>
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    <script nonce="{{ csrf_token() }}" src="{{ url(mix('js/dist/reservations-calendar.js')) }}"></script>
@stop
