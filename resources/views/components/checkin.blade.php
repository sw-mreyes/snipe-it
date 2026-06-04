@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/components/general.checkin') }}
    @parent
@stop

@section('header_right')
    <a href="{{ URL::previous() }}" class="btn btn-primary pull-right">{{ trans('general.back') }}</a>
@stop

{{-- Page content --}}
@section('content')

<x-container class="col-md-7">

    <x-form route="{{ route('components.checkin.store', [$component_assets->id, 'backto' => 'asset']) }}">

        <x-box header="{{ $snipe_component->name }}">

            <x-form.static :label="trans('general.checkin_from')">{{ $asset->present()->fullName }}</x-form.static>

            <!-- Qty -->
            <div class="form-group {{ $errors->has('checkin_qty') ? 'has-error' : '' }}">
                <label for="checkin_qty" class="col-md-3 control-label">{{ trans('general.qty') }}</label>
                <div class="col-md-3 text-right">
                    <input type="text" class="form-control" name="checkin_qty" aria-label="{{ trans('general.qty') }}" id="checkin_qty" value="{{ old('assigned_qty', $component_assets->assigned_qty) }}">
                </div>
                <div class="col-md-9 col-md-offset-2">
                    <p class="help-block">{{ trans('admin/components/general.checkin_limit', ['assigned_qty' => $component_assets->assigned_qty]) }}</p>
                    {!! $errors->first('checkin_qty', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                </div>
            </div>

            <x-form.row
                :label="trans('admin/hardware/form.notes')"
                :item="$snipe_component"
                name="note"
                type="textarea"
            />

            <x-slot:customfooter>
                <x-redirect_submit_options
                    index_route="components.index"
                    :button_label="trans('general.checkin')"
                    :options="[
                        'index' => trans('admin/hardware/form.redirect_to_all', ['type' => trans('general.components')]),
                        'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('general.component')]),
                    ]"
                />
            </x-slot:customfooter>

        </x-box>

    </x-form>

</x-container>

@stop
