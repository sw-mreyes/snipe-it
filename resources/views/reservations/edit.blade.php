@extends('layouts/edit-form', [
'createText' => trans('reservations.create'),
'updateText' => trans('reservations.update'),
'helpTitle' => trans('reservations.help'),
'helpText' => trans('reservations.help_text'),
'formAction' => ($item) ? route('reservations.update', ['reservation' => $item->id]) : route('reservations.store'),
])


{{-- Page content --}}
@section('inputFields')

@if($item)
<input type="hidden" id="reservation_id" name="reservation_id" value="{{$item->id}}">
@endif

@include ('partials.forms.edit.res-name', ['translated_name' => trans('general.name'), 'fieldname' => 'name'])
@include ('partials.forms.edit.res-user-select', ['translated_name' => trans('general.user'), 'fieldname' => 'user'])
@include ('partials.forms.edit.notes', ['translated_name' => trans('general.notes'), 'fieldname' => 'notes'])
@include ('partials.forms.edit.res-date-select',['translated_name' => trans('general.start_date'), 'fieldname' => 'start'])
@include ('partials.forms.edit.res-date-select',['translated_name' => trans('general.end_date'), 'fieldname' => 'end'])
@include ('partials.forms.edit.res-asset-select', ['translated_name' => trans('general.assets'), 'fieldname' => 'assets[]', 'multiple'=>true])

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
    function reservation_list_dom(data) {
        const ul = document.createElement('ul');
        for (let i in data) {
            const li = document.createElement('li');
            const name = data[i].name;
            const start = data[i].start;
            const end = data[i].end;
            const user = data[i].user.full_name;
            const li_content = document.createElement('div');
            // TODO: 
            //      to fancy this up a bit we could check the currently selected 
            //      start & end dates and highlight conflicting reservations in this list.
            //
            li_content.innerHTML = '<b>' + start + '</b> → <b>' + end + '</b>' + ' (' + name + ' by ' + user + ')'
            li.appendChild(li_content);
            ul.appendChild(li);
        }
        return ul;
    }

    const ras = 'res-asset-selection';
    document.getElementById(ras).onchange = function() {
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
    document.getElementById(ras).onchange();
</script>
@stop