@extends('layouts/default')

{{-- Page title --}}
@section('title')
  {{ trans('admin/maintenances/general.asset_maintenances') }}
  @parent
@stop


{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>

        <x-table
            name="maintenances"
            fixed_right_number="1"
            buttons="maintenanceButtons"
                api_url="{{ route('api.maintenances.index') }}"
                :presenter="\App\Presenters\MaintenancesPresenter::dataTableLayout()"
                export_filename="export-maintenances-{{ date('Y-m-d') }}"
            />

        </x-box>
    </x-container>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'maintenances-export', 'search' => true])
<script nonce="{{ csrf_token() }}">
    function maintenancesActionsFormatter(value, row) {
        var actions = '<nobr>';

        if ((row.available_actions) && (row.available_actions.update === true)) {
            actions += '<a href="{{ config('app.url') }}/maintenances/' + row.id + '/edit" class="actions btn btn-sm btn-warning hidden-print" data-tooltip="true" title="{{ trans('general.update') }}"><x-icon type="edit" class="fa-fw" /><span class="sr-only">{{ trans('general.update') }}</span></a>&nbsp;';
        }

        if ((row.available_actions) && (row.available_actions.complete === true)) {
            actions += '<form style="display:inline;" method="POST" action="{{ config('app.url') }}/maintenances/' + row.id + '/complete">';
            actions += '{{ csrf_field() }}';
            actions += '<button type="submit" class="actions btn btn-sm btn-success hidden-print" data-tooltip="true" title="{{ trans('admin/maintenances/form.mark_complete') }}"><x-icon type="checkmark" class="fa-fw" /><span class="sr-only">{{ trans('admin/maintenances/form.mark_complete') }}</span></button>&nbsp;';
            actions += '</form>';
        }

        if ((row.available_actions) && (row.available_actions.delete === true)) {
            actions += '<a href="{{ config('app.url') }}/maintenances/' + row.id + '" '
                + ' class="actions btn btn-danger btn-sm delete-asset hidden-print" data-tooltip="true" '
                + ' data-toggle="modal" data-icon="fa-trash"'
                + ' data-content="{{ trans('general.sure_to_delete') }}: ' + row.name + '?" '
                + ' data-title="{{ trans('general.delete') }}" onClick="return false;">'
                + '<x-icon type="delete" class="fa-fw" /><span class="sr-only">{{ trans('general.delete') }}</span></a>&nbsp;';
        }

        actions += '</nobr>';
        return actions;
    }
</script>
@stop
