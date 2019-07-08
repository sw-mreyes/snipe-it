@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('reservations.calendar') }}
@parent
@stop

@section('header_right')
<a href="{{ route('reservations.create') }}" class="btn btn-primary pull-right"></i> {{ trans('general.create') }}</a>
<a href="{{ route('reservations.index') }}" class="btn btn-primary pull-right"></i> {{ trans('reservations.list') }}</a>
@stop

{{-- Page content --}}
@section('content')

<script src="https://uicdn.toast.com/tui.code-snippet/latest/tui-code-snippet.js"></script>
<script src="https://uicdn.toast.com/tui-calendar/latest/tui-calendar.js"></script>
<link rel="stylesheet" type="text/css" href="https://uicdn.toast.com/tui-calendar/latest/tui-calendar.css" />

<div id="menu">
    <span id="menu-navi">
        <button type="button" class="btn btn-default btn-sm move-today" data-action="move-today">Today</button>
        <button type="button" class="btn btn-default btn-sm move-day" data-action="move-prev">
            <i class="calendar-icon ic-arrow-line-left" data-action="move-prev"></i>
        </button>
        <button type="button" class="btn btn-default btn-sm move-day" data-action="move-next">
            <i class="calendar-icon ic-arrow-line-right" data-action="move-next"></i>
        </button>
    </span>
    <span id="renderRange" class="render-range"></span>
</div>
<div id="calendar" style="height: 800px;"></div>

@stop
@section('moar_scripts')
<script type="text/javascript">
    //
    var Calendar = tui.Calendar;
    var calendar = new Calendar('#calendar', {
        defaultView: 'month',
        taskView: true,
        usageStatistics: false,
        isReadOnly: true,
        disableDblClick: true,
        disableClick: true,
    });
    // Get reservations
    $.ajax({
        type: 'GET',
        url: '{{  route( "api.reservations.calendar" ) }}',
        headers: {
            "X-Requested-With": 'XMLHttpRequest',
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
        },
        dataType: 'json',
        success: function(data) {
            console.table(data['rows']);
            calendar.createSchedules(data['rows']);
        },
        error: function(data) {
            // window.location.reload(true);
        }
    });
</script>


@stop