{{-- Reservations for one asset (custom fork feature). Expects $asset in scope.

     Only current and upcoming reservations are listed — a closed booking is
     history, and the asset's own history tab already covers that. --}}
@php
    $assetReservations = \App\Models\Reservation::with('user')
        ->forAsset($asset->id)
        ->currentAndUpcoming()
        ->orderBy('start')
        ->get();
@endphp

<x-box>
    <x-slot:header>
        <x-icon type="calendar" class="fa-fw" /> {{ trans('reservations.reservations') }}
    </x-slot:header>

    @if ($assetReservations->isEmpty())
        <p class="text-muted">{{ trans('reservations.none_for_asset') }}</p>
    @else
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ trans('reservations.name') }}</th>
                    <th>{{ trans('reservations.user') }}</th>
                    <th>{{ trans('reservations.start') }}</th>
                    <th>{{ trans('reservations.end') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assetReservations as $reservation)
                    <tr>
                        <td>
                            <a href="{{ route('reservations.show', ['reservation' => $reservation->id]) }}">
                                {{ $reservation->name }}
                            </a>
                            @if ($reservation->start?->isPast())
                                <span class="label label-success">{{ trans('reservations.status.active') }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($reservation->user)
                                <a href="{{ route('users.show', $reservation->user->id) }}">
                                    {{ $reservation->user->present()->fullName }}
                                </a>
                            @endif
                        </td>
                        <td>{{ $reservation->start?->format('Y-m-d H:i') }}</td>
                        <td>{{ $reservation->end?->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @can('checkout', \App\Models\Asset::class)
        <a href="{{ route('reservations.create', ['asset' => $asset->id]) }}" class="btn btn-primary">
            <x-icon type="plus" class="fa-fw" /> {{ trans('reservations.reserve_this_asset') }}
        </a>
    @endcan
</x-box>
