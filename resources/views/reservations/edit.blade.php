@extends('layouts/edit-form', [
'createText' => trans('reservations.create'),
'updateText' => trans('reservations.update'),
'helpTitle' => trans('reservations.help'),
'helpText' => trans('reservations.help_text'),
'formAction' => ($item) ? route('reservations.update', ['reservation' => $item->id]) : route('reservations.store'),
])

{{-- Page content --}}
@section('inputFields')

@include ('partials.forms.edit.name-no-check', ['translated_name' => trans('general.name'), 'fieldname' => 'name'])
@include ('partials.forms.edit.user-select', ['translated_name' => trans('general.user'), 'fieldname' => 'user'])
@include ('partials.forms.edit.date-select',['translated_name' => trans('general.start_date'), 'fieldname' => 'start'])
@include ('partials.forms.edit.date-select',['translated_name' => trans('general.end_date'), 'fieldname' => 'end'])
@include ('partials.forms.edit.notes', ['translated_name' => trans('general.notes'), 'fieldname' => 'notes'])
@include ('partials.forms.edit.asset-select', ['translated_name' => trans('general.assets'), 'fieldname' => 'assets[]', 'multiple'=>true])

@stop