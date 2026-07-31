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

            {{-- FullCalendar mounts here; initialized by the bundled JS, which
                 loads only the visible window from the reservations API.
                 ?highlight=<id> marks one reservation, e.g. when linked from
                 the detail page. --}}
            <div id="reservations-calendar"
                 data-events-url="{{ route('api.reservations.index') }}"
                 data-highlight-id="{{ request('highlight') }}"
                 data-locale="{{ str_replace('_', '-', app()->getLocale()) }}"></div>

            {{-- Filled in by the JS when an event is clicked. --}}
            <div id="reservation-event-details" style="margin-top: 15px;"></div>

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    <script nonce="{{ csrf_token() }}" src="{{ url(mix('js/dist/reservations-calendar.js')) }}"></script>
@stop
