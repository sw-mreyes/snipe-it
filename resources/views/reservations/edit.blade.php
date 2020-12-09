@extends('layouts/edit-form', [
'createText' => trans('reservations.create'),
'updateText' => trans('reservations.update'),
'helpTitle' => trans('reservations.help'),
'helpText' => trans('reservations.help_text'),
'formAction' => ($item) ? route('reservations.update', ['reservation' => $item->id||null]) : route('reservations.store'),
])


{{-- Page content --}}
@include ('partials.tui-init', ['calendar'=>false, 'datepicker'=>true])



@section('inputFields')

@if($item)
<input type="hidden" id="reservation_id" name="reservation_id" value="{{$item->id}}">
@endif

@include ('partials.forms.edit.res-name', ['translated_name' => trans('general.name'), 'fieldname' => 'name'])
@include ('partials.forms.edit.res-user-select', ['translated_name' => trans('general.user'), 'fieldname' => 'user'])
@include ('partials.forms.edit.notes', ['translated_name' => trans('general.notes'), 'fieldname' => 'notes'])
@include ('partials.forms.edit.res-asset-select', ['translated_name' => trans('general.assets'), 'fieldname' => 'assets[]', 'multiple'=>true])
@include ('partials.forms.edit.res-date-range-select')

<div class="row" id='asset-res-root' hidden="true">
    <div class="box">
        <div class='col-md-8'>
            <h5>{{trans('reservations.edit_asset_reservations')}}:</h5>
            <div class='col-md-40'>
                <ul id='res-entries'>
                </ul>
            </div>
        </div>
    </div>
</div>

@stop


@section('moar_scripts')
<script>
    function btag(data, color = 'black') {
        return '<b style="color:' + color + '">' + data + '</b>'
    }

    function format_date(x) {
        const d = new Date(x);
        return d.getFullYear() + '-' +
            ("00" + (d.getMonth() + 1)).slice(-2) + "-" +
            ("00" + d.getDate()).slice(-2) + " " +
            ("00" + d.getHours()).slice(-2) + ":" +
            ("00" + d.getMinutes()).slice(-2) + ":" +
            ("00" + d.getSeconds()).slice(-2)
    }

    function reservation_list_dom(data) {
        const ul = document.createElement('ul');
        for (let i in data) {
            const li = document.createElement('li');
            const name = data[i].name;
            const user = data[i].user.full_name;
            const li_content = document.createElement('div');
            const res_id = data[i].id;
            const start = Date.parse(data[i].start);
            const end = Date.parse(data[i].end);
            const selected_start = Date.parse(document.getElementById('start-input').value);
            const selected_end = Date.parse(document.getElementById('end-input').value);
            // Highlight dates if they overlap with the selection / TODO: force update on date selection changes.
            let highlight = 'black';
            //
            if ((selected_start >= start && selected_start <= end) || (selected_end >= start && selected_end <= end)) {
                highlight = 'red';
            }
            if (res_id == "{{ $item->id }}") {
                highlight = 'green';
            }
            //
            li_content.innerHTML = btag(format_date(start), highlight) + ' → ' + btag(format_date(end), highlight) + ' (' + name + ' by ' + user + ')'
            //
            li.appendChild(li_content);
            ul.appendChild(li);

        }
        return ul;
    }


    const ras = 'res-asset-selection';
    const update_reservation_list = function() {
        // we also could show a calendar here..
        document.getElementById("res-entries").innerHTML = "";
        const root = document.getElementById(ras);
        const options = root.getElementsByTagName('option');
        for (let index in options) {
            const opt = options[index];
            if (opt.selected) {
                // --- Get the reservations for the selected asset ---
                $.ajax({
                    type: 'GET',
                    url: '{{  route( "api.reservations.assetReservations" ) }}?asset=' + opt.value,
                    headers: {
                        "X-Requested-With": 'XMLHttpRequest',
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data['reservations']['total'] <= 0) return;
                        else {
                            document.getElementById('asset-res-root').hidden = false;
                        }
                        const li = document.createElement('li');
                        li.appendChild(document.createTextNode(data['asset']['name'].length > 0 ? data['asset']['name'] : data['asset']['asset_tag']));
                        li.appendChild(reservation_list_dom(data['reservations']['rows']));
                        document.getElementById('res-entries').appendChild(li);
                    },
                    error: function(data) {
                        console.log("error:");
                        console.log(data);
                    }
                });
                // --- ---
            }
        }
    }
    //
    document.getElementById('res-asset-selection').onchange = update_reservation_list;
    document.getElementById('container').onmouseleave = update_reservation_list;
    update_reservation_list();
</script>
@stop