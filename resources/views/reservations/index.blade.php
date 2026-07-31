@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('reservations.reservations') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>

            @include('reservations.partials.toolbar', ['active' => 'list'])

            {{-- Free-text advanced search is off: the API takes a single `search`
                 term, not the per-column filters that widget emits. --}}
            <x-table
                name="reservations"
                :presenter="\App\Presenters\ReservationPresenter::dataTableLayout()"
                :api_url="route('api.reservations.index')"
                show_advanced_search="false"
                show_column_search="false"
                sort_field="start"
                sort_order="asc"
                fixed_right_number="1"
                export_filename="reservations-{{ date('Y-m-d') }}"
            />

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table')
@stop
