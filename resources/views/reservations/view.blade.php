@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ $reservation->name }}
    @parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>
            <table class="table table-striped">
                <tbody>
                    <tr>
                        <td>{{ trans('reservations.name') }}</td>
                        <td>{{ $reservation->name }}</td>
                    </tr>
                    <tr>
                        <td>{{ trans('reservations.user') }}</td>
                        <td>
                            @if ($reservation->user)
                                {{ $reservation->user->first_name }} {{ $reservation->user->last_name }} ({{ $reservation->user->username }})
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>{{ trans('reservations.start') }}</td>
                        <td>{{ $reservation->start?->format('Y-m-d H:i') }}</td>
                    </tr>
                    <tr>
                        <td>{{ trans('reservations.end') }}</td>
                        <td>{{ $reservation->end?->format('Y-m-d H:i') }}</td>
                    </tr>
                    <tr>
                        <td>{{ trans('reservations.assets') }}</td>
                        <td>
                            @foreach ($reservation->assets as $asset)
                                <a href="{{ route('hardware.show', ['asset' => $asset->id]) }}">{{ $asset->asset_tag }}</a>@if (! $loop->last), @endif
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td>{{ trans('reservations.notes') }}</td>
                        <td>{{ $reservation->notes }}</td>
                    </tr>
                </tbody>
            </table>

            @can('checkout', \App\Models\Asset::class)
                <a href="{{ route('reservations.edit', ['reservation' => $reservation->id]) }}" class="btn btn-warning">
                    <x-icon type="edit" /> {{ trans('general.edit') }}
                </a>
            @endcan
        </x-box>
    </x-container>
@stop
