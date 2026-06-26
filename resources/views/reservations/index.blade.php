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

            <table
                data-cookie-id-table="reservationsListingTable"
                data-id-table="reservationsListingTable"
                data-side-pagination="server"
                data-sort-order="asc"
                data-sort-name="start"
                id="reservationsListingTable"
                data-url="{{ route('api.reservations.index') }}"
                class="table table-striped snipe-table"
                data-export-options='{
                    "fileName": "reservations-export-{{ date('Y-m-d') }}",
                    "ignoreColumn": ["actions"]
                }'>
                <thead>
                    <tr>
                        <th data-field="name" data-sortable="true" data-formatter="reservationsLinkFormatter">{{ trans('reservations.name') }}</th>
                        <th data-field="user" data-formatter="reservationUserFormatter">{{ trans('reservations.user') }}</th>
                        <th data-field="assets" data-formatter="reservationAssetsFormatter">{{ trans('reservations.assets') }}</th>
                        <th data-field="start" data-sortable="true" data-formatter="dateDisplayFormatter">{{ trans('reservations.start') }}</th>
                        <th data-field="end" data-sortable="true" data-formatter="dateDisplayFormatter">{{ trans('reservations.end') }}</th>
                        <th data-field="actions" data-formatter="reservationsActionsFormatter" class="text-right">{{ trans('table.actions') }}</th>
                    </tr>
                </thead>
            </table>
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table')
@stop
