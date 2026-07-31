@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ $item->id ? trans('reservations.update') : trans('reservations.create') }}
    @parent
@stop

{{-- Page content --}}
@section('content')

    @php
        // Preselected assets: submitted input after a validation failure,
        // otherwise the reservation's own assets, otherwise ?asset= from the
        // asset page.
        $selectedAssets = old('assets', $item->id
            ? $item->assets->pluck('id')->all()
            : ($forAsset ? [$forAsset->id] : []));
        $selectedUser = old('user_id', $item->user_id);
    @endphp

    <x-container class="col-md-10 col-md-offset-1">
        <x-form
            :item="$item"
            :route="$item->id
                ? route('reservations.update', ['reservation' => $item->id])
                : route('reservations.store')"
        >
            <x-box :header="$item->id ? trans('reservations.update') : trans('reservations.create')">

                <x-form.row
                    :label="trans('reservations.name')"
                    :item="$item"
                    name="name"
                    required
                />

                {{-- Who the assets are reserved for --}}
                <div class="form-group {{ $errors->has('user_id') ? 'has-error' : '' }}">
                    <x-form.label for="user_id" class="col-md-3">{{ trans('reservations.user') }}</x-form.label>
                    <div class="col-md-8">
                        <select class="js-data-ajax" data-endpoint="users" name="user_id" id="user_id"
                                data-placeholder="{{ trans('reservations.select_user') }}"
                                style="width: 100%" aria-label="user_id" required>
                            <option value=""></option>
                            @if ($selectedUser && ($selected = \App\Models\User::find($selectedUser)))
                                <option value="{{ $selected->id }}" selected>
                                    {{ $selected->present()->fullName }} ({{ $selected->username }})
                                </option>
                            @endif
                        </select>
                        {{-- Plain markup, not <x-icon>: Blade compiles component
                             tags even inside quoted strings, which mangles the
                             surrounding echo and prints it literally. --}}
                        {!! $errors->first('user_id', '<span class="alert-msg"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                    </div>
                </div>

                {{-- Assets being reserved --}}
                <div class="form-group {{ $errors->has('assets') ? 'has-error' : '' }}">
                    <x-form.label for="assets" class="col-md-3">{{ trans('reservations.assets') }}</x-form.label>
                    <div class="col-md-8">
                        <select class="js-data-ajax" data-endpoint="hardware" name="assets[]" id="assets"
                                data-placeholder="{{ trans('reservations.select_assets') }}"
                                style="width: 100%" aria-label="assets" multiple required>
                            @foreach ($selectedAssets as $assetId)
                                @if ($asset = \App\Models\Asset::find($assetId))
                                    <option value="{{ $asset->id }}" selected>
                                        {{ \App\Services\AssetLabel::for($asset) }}@if ($asset->name && $asset->asset_tag) ({{ $asset->asset_tag }})@endif
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        {!! $errors->first('assets', '<span class="alert-msg"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                    </div>
                </div>

                {{-- Window. Native datetime-local: no JS dependency, and the
                     browser renders it 24-hour under a German locale. --}}
                <x-form.row
                    :label="trans('reservations.start')"
                    :item="$item"
                    name="start"
                    type="datetime-local"
                    :value="old('start', $item->start?->format('Y-m-d\TH:i'))"
                    required
                />

                <x-form.row
                    :label="trans('reservations.end')"
                    :item="$item"
                    name="end"
                    type="datetime-local"
                    :value="old('end', $item->end?->format('Y-m-d\TH:i'))"
                    required
                />

                <x-form.row
                    :label="trans('reservations.notes')"
                    :item="$item"
                    name="notes"
                    type="textarea"
                />

                {{-- Existing reservations for the selected assets; overlapping
                     ones are flagged. Populated by reservations-form.js. --}}
                <div class="form-group">
                    <div class="col-md-8 col-md-offset-3">
                        <div id="reservation-conflicts"
                             data-forasset-template="{{ route('api.reservations.forasset', ['asset_id' => '__ASSET_ID__']) }}"
                             data-reservation-id="{{ $item->id ?? '' }}"
                             data-heading="{{ trans('reservations.conflicts_heading') }}"
                             data-overlap="{{ trans('reservations.conflicts_overlap') }}"
                             data-none="{{ trans('reservations.conflicts_none') }}"></div>
                    </div>
                </div>

                <x-slot:customfooter>
                    <div class="box-footer text-right">
                        <a class="btn btn-link" href="{{ route('reservations.index') }}">
                            {{ trans('button.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <x-icon type="checkmark" class="fa-fw" /> {{ trans('general.save') }}
                        </button>
                    </div>
                </x-slot:customfooter>

            </x-box>
        </x-form>
    </x-container>
@stop

@section('moar_scripts')
    <script nonce="{{ csrf_token() }}" src="{{ url(mix('js/dist/reservations-form.js')) }}"></script>
@stop
