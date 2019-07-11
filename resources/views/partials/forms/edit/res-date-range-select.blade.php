<!-- Purchase Date -->
<div class="form-group">
    <label for="res-date" class="col-md-3 control-label">{{ trans('reservations.start') }}</label>
    <div class="input-group col-md-3">

        <div class="tui-datepicker-input tui-datetime-input tui-has-focus">
            <input name='start' type="text" id="start-input" aria-label="Date-Time" value="{{ $item->start }}">
            <span class="tui-ico-date"></span>
        </div>

    </div>
    <br>
    <label for="res-date" class="col-md-3 control-label">{{ trans('reservations.end') }}</label>
    <div class="input-group col-md-3">

        <div class="tui-datepicker-input tui-datetime-input tui-has-focus">
            <input name='end' type="text" id="end-input" aria-label="Date-Time" value="{{ $item->end }}">
            <span class="tui-ico-date"></span>
        </div>
    </div>
</div>

<!-- Container for the date selection calendar & time picker. z-index & postion required to stay "on top" -->
<div id="container" style="margin-top: -1px; z-index: 999999999;position: fixed;"></div>

<script>
    const today = new Date();
    const pnames = ['start', 'end'];
    const dates = {
        start: Date.parse("{{ $item->start }}"),
        end: Date.parse("{{ $item->end }}"),
    }
    const pickers = {}
    //
    for (let i in pnames) {
        const datepicker = new tui.DatePicker('#container', {
            usageStatistics: false,
            date: dates[pnames[i]],
            selectableRanges: [
                [today, new Date(today.getFullYear() + 1, today.getMonth(), today.getDate())]
            ],
            input: {
                element: '#' + pnames[i] + '-input',
                format: 'yyyy-MM-dd HH:mm'
            },
            timepicker: {
                showMeridiem: false
            }
        });
        pickers[pnames[i]] = datepicker;
    }
    //
</script>