@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ $reservation->name }}
    @parent
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box :header="$reservation->name">

            <div class="row">
                <div class="col-md-8">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td style="width: 200px;"><strong>{{ trans('general.status') }}</strong></td>
                                <td>
                                    @php($status = $reservation->end?->isPast() ? 'past' : ($reservation->start?->isFuture() ? 'upcoming' : 'active'))
                                    <span class="label label-{{ $status === 'active' ? 'success' : ($status === 'upcoming' ? 'info' : 'default') }}">
                                        {{ trans('reservations.status.'.$status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>{{ trans('reservations.user') }}</strong></td>
                                <td>
                                    @if ($reservation->user)
                                        <a href="{{ route('users.show', $reservation->user->id) }}">
                                            {{ $reservation->user->present()->fullName }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>{{ trans('reservations.start') }}</strong></td>
                                <td>{{ $reservation->start?->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ trans('reservations.end') }}</strong></td>
                                <td>{{ $reservation->end?->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ trans('reservations.assets') }}</strong></td>
                                <td>
                                    <ul class="list-unstyled" style="margin-bottom: 0;">
                                        @foreach ($reservation->assets as $asset)
                                            <li>
                                                <x-icon type="assets" class="fa-fw text-muted" />
                                                <a href="{{ route('hardware.show', ['asset' => $asset->id]) }}">
                                                    {{ \App\Services\AssetLabel::for($asset) }}
                                                </a>
                                                @if ($asset->name && $asset->asset_tag)
                                                    <span class="text-muted">{{ $asset->asset_tag }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                            @if ($reservation->notes)
                                <tr>
                                    <td><strong>{{ trans('reservations.notes') }}</strong></td>
                                    <td>{{ $reservation->notes }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="col-md-4">
                    <a href="{{ route('reservations.calendar', ['highlight' => $reservation->id]) }}"
                       class="btn btn-default btn-block">
                        <x-icon type="calendar" class="fa-fw" /> {{ trans('reservations.calendar') }}
                    </a>

                    @can('checkout', \App\Models\Asset::class)
                        <a href="{{ route('reservations.edit', ['reservation' => $reservation->id]) }}"
                           class="btn btn-warning btn-block">
                            <x-icon type="edit" class="fa-fw" /> {{ trans('general.update') }}
                        </a>

                        <form method="POST" action="{{ route('reservations.destroy', ['reservation' => $reservation->id]) }}"
                              onsubmit="return confirm('{{ trans('reservations.delete_confirm') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-block">
                                <x-icon type="delete" class="fa-fw" /> {{ trans('general.delete') }}
                            </button>
                        </form>
                    @endcan
                </div>
            </div>

        </x-box>
    </x-container>
@stop
