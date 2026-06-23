@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('reservations.reservations') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>
            <div class="row" style="margin-bottom: 15px;">
                <div class="col-md-12 text-right">
                    <a href="{{ route('reservations.calendar') }}" class="btn btn-default">
                        <x-icon type="calendar" /> {{ trans('reservations.calendar') }}
                    </a>
                    @can('checkout', \App\Models\Asset::class)
                        <a href="{{ route('reservations.create') }}" class="btn btn-primary">
                            {{ trans('reservations.create') }}
                        </a>
                    @endcan
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ trans('reservations.name') }}</th>
                            <th>{{ trans('reservations.user') }}</th>
                            <th>{{ trans('reservations.assets') }}</th>
                            <th>{{ trans('reservations.start') }}</th>
                            <th>{{ trans('reservations.end') }}</th>
                            <th class="text-right">{{ trans('table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reservations as $reservation)
                            <tr>
                                <td>
                                    <a href="{{ route('reservations.show', ['reservation' => $reservation->id]) }}">
                                        {{ $reservation->name }}
                                    </a>
                                </td>
                                <td>
                                    @if ($reservation->user)
                                        {{ $reservation->user->first_name }} {{ $reservation->user->last_name }}
                                    @endif
                                </td>
                                <td>
                                    @foreach ($reservation->assets as $asset)
                                        <a href="{{ route('hardware.show', ['hardware' => $asset->id]) }}">{{ $asset->asset_tag }}</a>@if (! $loop->last), @endif
                                    @endforeach
                                </td>
                                <td>{{ $reservation->start?->format('Y-m-d H:i') }}</td>
                                <td>{{ $reservation->end?->format('Y-m-d H:i') }}</td>
                                <td class="text-right">
                                    @can('checkout', \App\Models\Asset::class)
                                        <a href="{{ route('reservations.edit', ['reservation' => $reservation->id]) }}" class="btn btn-sm btn-warning">
                                            <x-icon type="edit" />
                                        </a>
                                        <form method="POST" action="{{ route('reservations.destroy', ['reservation' => $reservation->id]) }}" style="display:inline;" onsubmit="return confirm('{{ trans('general.sure_to_delete') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <x-icon type="delete" />
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">{{ trans('reservations.none') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-box>
    </x-container>
@stop
