@extends('layouts/default')

{{-- Page title --}}
@section('title')
    @if ($item->id)
        {{ trans('reservations.update') }}
    @else
        {{ trans('reservations.create') }}
    @endif
    @parent
@stop

{{-- Page content --}}
@section('content')

    @php
        // Preselected assets: submitted input on validation failure, otherwise the
        // reservation's current assets, otherwise the asset passed via ?asset=.
        $selectedAssets = old('assets', $item->id ? $item->assets->pluck('id')->all() : ($forAsset ? [$forAsset->id] : []));
        $selectedUser = old('user_id', $item->user_id);
    @endphp

    <x-container class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1">

        <x-form :$item route="{{ $item->id ? route('reservations.update', ['reservation' => $item->id]) : route('reservations.store') }}">

            <x-box>

                <x-form.row
                    :label="trans('reservations.name')"
                    :$item
                    name="name"
                    required
                />

                {{-- User the assets are reserved for --}}
                <div class="form-group {{ $errors->has('user_id') ? 'has-error' : '' }}">
                    <label for="user_id" class="col-md-3 control-label">{{ trans('reservations.user') }}</label>
                    <div class="col-md-6">
                        <select class="js-data-ajax" data-endpoint="users" data-placeholder="{{ trans('general.select_user') }}"
                                name="user_id" id="user_id" style="width: 100%" aria-label="user_id" required>
                            <option value=""></option>
                            @if ($selectedUser && ($u = \App\Models\User::find($selectedUser)))
                                <option value="{{ $u->id }}" selected>{{ $u->first_name }} {{ $u->last_name }} ({{ $u->username }})</option>
                            @endif
                        </select>
                    </div>
                    {!! $errors->first('user_id', '<div class="col-md-8 col-md-offset-3"><span class="alert-msg"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}
                </div>

                {{-- Assets being reserved (multiple) --}}
                <div class="form-group {{ $errors->has('assets') ? 'has-error' : '' }}">
                    <label for="assets" class="col-md-3 control-label">{{ trans('reservations.assets') }}</label>
                    <div class="col-md-6">
                        <select class="js-data-ajax" data-endpoint="hardware" data-placeholder="{{ trans('general.select_asset') }}"
                                name="assets[]" id="assets" style="width: 100%" aria-label="assets" multiple required>
                            @foreach ($selectedAssets as $assetId)
                                @if ($a = \App\Models\Asset::find($assetId))
                                    <option value="{{ $a->id }}" selected>{{ $a->asset_tag }} @if ($a->name) ({{ $a->name }}) @endif</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    {!! $errors->first('assets', '<div class="col-md-8 col-md-offset-3"><span class="alert-msg"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}
                </div>

                {{-- Reservation window --}}
                <div class="form-group {{ $errors->has('start') ? 'has-error' : '' }}">
                    <label for="start" class="col-md-3 control-label">{{ trans('reservations.start') }}</label>
                    <div class="col-md-6">
                        <input type="datetime-local" class="form-control" name="start" id="start"
                               value="{{ old('start', $item->start?->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    {!! $errors->first('start', '<div class="col-md-8 col-md-offset-3"><span class="alert-msg"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}
                </div>

                <div class="form-group {{ $errors->has('end') ? 'has-error' : '' }}">
                    <label for="end" class="col-md-3 control-label">{{ trans('reservations.end') }}</label>
                    <div class="col-md-6">
                        <input type="datetime-local" class="form-control" name="end" id="end"
                               value="{{ old('end', $item->end?->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    {!! $errors->first('end', '<div class="col-md-8 col-md-offset-3"><span class="alert-msg"><i class="fas fa-times" aria-hidden="true"></i> :message</span></div>') !!}
                </div>

                <x-form.row
                    :label="trans('reservations.notes')"
                    :$item
                    name="notes"
                    type="textarea"
                />

                <x-slot:customfooter>
                    <x-redirect_submit_options
                        index_route="reservations.index"
                        :button_label="trans('general.save')"
                        :options="[
                            'index' => trans('reservations.reservations'),
                        ]"
                    />
                </x-slot:customfooter>

            </x-box>

        </x-form>

    </x-container>

@stop
