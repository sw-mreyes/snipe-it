@extends('layouts/edit-form', [
'createText' => trans('reservations.create'),
'updateText' => trans('reservations.update'),
'helpTitle' => trans('reservations.help'),
'helpText' => trans('reservations.help_text'),
'formAction' => ($item) ? route('reservations.update', ['reservation' => $item->id]) : route('reservations.store'),
])

{{-- Page title --}}
@section('title')
{{ trans('reservations.create') }}
@parent
@stop
{{-- Page content --}}
@section('inputFields')

@include ('partials.forms.edit.user-select', ['translated_name' => trans('general.user'), 'fieldname' => 'user'])
@include ('partials.forms.edit.date-select',['translated_name' => trans('general.start_date'), 'fieldname' => 'start'])
@include ('partials.forms.edit.date-select',['translated_name' => trans('general.end_date'), 'fieldname' => 'end'])
@include ('partials.forms.edit.notes', ['translated_name' => trans('general.notes'), 'fieldname' => 'notes'])

@stop