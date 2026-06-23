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
            {{-- FullCalendar mounts here; initialized in the bundled JS (see task 9).
                 Data is loaded from the reservations API. --}}
            <div id="reservations-calendar" data-events-url="{{ route('api.reservations.index') }}"></div>
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    <script nonce="{{ csrf_token() }}" src="{{ url(mix('js/dist/reservations-calendar.js')) }}"></script>
@stop
