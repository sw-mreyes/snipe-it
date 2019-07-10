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

<script src="{{ url(asset('js/tui-code-snippet.js')) }}" nonce="{{ csrf_token() }}"></script>
<script src="{{ url(asset('js/tui-calendar.js')) }}" nonce="{{ csrf_token() }}"></script>
<link rel="stylesheet" href="{{ url(asset('css/tui-calendar.css')) }}">

<div id="menu" hidden="true">
    <span id="menu-navi">
        <button type="button" class="btn btn-default btn-sm move-today" id="calendar-today">Today</button>
        <button type="button" class="btn btn-default btn-sm move-day" id='calendar-prev'>
            <i class="fa fa-arrow-left" data-action="move-prev"></i>
        </button>
        <button type="button" class="btn btn-default btn-sm move-day" id='calendar-next'>
            <i class="fa fa-arrow-right" data-action="move-next"></i>
        </button>
    </span>
    <span id="renderRange" class="render-range"></span>
</div>
<div id="calendar" style="height: 800px;"></div>
@stop

@section('moar_scripts')
<script type="text/javascript">
    //
    const templates = {
        popupDetailBody: function(schedule) {
            return schedule.body;
        },
        popupDetailUser: function(schedule) {
            return schedule.attendees;
        },
    };
    //
    var Calendar = tui.Calendar;
    var calendar = new Calendar('#calendar', {
        defaultView: 'month',
        taskView: true,
        usageStatistics: false,
        /**disable google analytics */
        isReadOnly: true,
        disableDblClick: true,
        disableClick: true,
        month: {
            startDayOfWeek: 1,
            narrowWeekend: true,
        },
        useDetailPopup: true,
        template: templates
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
            for (let i in data['rows']) {
                data['rows'][i].bgColor = rainbow(data['rows'].length / 1.25, i);
            }
            calendar.createSchedules(data['rows']);
        },
    });

    // The calender methods dont work for some reason.
    //document.getElementById('calendar-next').onclick = calendar.next;
    //document.getElementById('calendar-prev').onclick = calendar.prev;
    //document.getElementById('calendar-today').onclick = calendar.today;

    /* Replaced by rainbow function.
    const colors = [
        'sienna', 'MediumPurple', 'cyan', 'orange', 'teal',
        'fuchsia', 'olive', 'lightblue', 'DarkSlateBlue', 'DarkSlateGray'
    ]
    var color_index = 0;

    function next_color() {
        if (color_index >= colors.length) color_index = 0;
        return colors[color_index++]

    }
    */

    // https://stackoverflow.com/questions/1484506/random-color-generator
    function rainbow(numOfSteps, step) {
        // This function generates vibrant, "evenly spaced" colours (i.e. no clustering). This is ideal for creating easily distinguishable vibrant markers in Google Maps and other apps.
        // Adam Cole, 2011-Sept-14
        // HSV to RBG adapted from: http://mjijackson.com/2008/02/rgb-to-hsl-and-rgb-to-hsv-color-model-conversion-algorithms-in-javascript
        var r, g, b;
        var h = step / numOfSteps;
        var i = ~~(h * 6);
        var f = h * 6 - i;
        var q = 1 - f;
        switch (i % 6) {
            case 0:
                r = 1;
                g = f;
                b = 0;
                break;
            case 1:
                r = q;
                g = 1;
                b = 0;
                break;
            case 2:
                r = 0;
                g = 1;
                b = f;
                break;
            case 3:
                r = 0;
                g = q;
                b = 1;
                break;
            case 4:
                r = f;
                g = 0;
                b = 1;
                break;
            case 5:
                r = 1;
                g = 0;
                b = q;
                break;
        }
        var c = "#" + ("00" + (~~(r * 255)).toString(16)).slice(-2) + ("00" + (~~(g * 255)).toString(16)).slice(-2) + ("00" + (~~(b * 255)).toString(16)).slice(-2);
        return (c);
    }
</script>
@stop